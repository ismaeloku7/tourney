<?php

namespace App\Filament\Resources\Categorias\RelationManagers;

use App\Actions\Competicion\CerrarFase;
use App\Competicion\Datos\ConfiguracionDeGrupos;
use App\Competicion\Errores\ConfiguracionInvalida;
use App\Competicion\Errores\ErrorDeCompeticion;
use App\Enums\EstadoFase;
use App\Enums\ReglaDeCruces;
use App\Enums\TipoDeFase;
use App\Filament\Avisos;
use App\Filament\Competicion\SorteoDeGrupos;
use App\Filament\Resources\Categorias\Schemas\FaseForm;
use App\Filament\Resources\Torneos\Pages\PartidosDelTorneo;
use App\Models\Categoria;
use App\Models\Fase;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Las fases de la categoría, en orden: grupos y luego cuadro, una liga,
 * un cuadro directo… Cada una con su configuración validada por el motor,
 * y desde aquí se sortea, se cierra y se mira la clasificación o el
 * cuadro. Una fase ya sorteada no cambia de configuración.
 */
class FasesRelationManager extends RelationManager
{
    protected static string $relationship = 'fases';

    protected static ?string $title = 'Fases';

    protected static ?string $modelLabel = 'fase';

    protected static ?string $pluralModelLabel = 'fases';

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components(FaseForm::campos());
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('orden')
            ->reorderable('orden')
            ->recordTitleAttribute('nombre')
            ->emptyStateHeading('Sin fases')
            ->emptyStateDescription('Lo habitual: una fase de grupos y un cuadro con los clasificados. Un torneo pequeño puede ser solo una liga o solo un cuadro.')
            ->columns([
                TextColumn::make('orden')->label('Orden'),
                TextColumn::make('nombre')->label('Fase'),
                TextColumn::make('tipo')->label('Tipo')->badge()->formatStateUsing(fn (TipoDeFase $state): string => $state->etiqueta()),
                TextColumn::make('resumen')->label('Configuración')->state(fn (Fase $record): string => FaseForm::resumen($record)),
                TextColumn::make('formatoPartido.nombre')->label('Formato')->placeholder('El de la categoría'),
                TextColumn::make('estado')->label('Estado')->badge()
                    ->formatStateUsing(fn (EstadoFase $state): string => $state->etiqueta())
                    ->color(fn (EstadoFase $state): string => $state->color()),
                ToggleColumn::make('visible_en_web')->label('En la web')
                    ->tooltip('Desmarcada, sus grupos, su cuadro y sus partidos no salen en la web, el tablero ni el portal de los jugadores.'),
                TextColumn::make('partidos_count')->label('Partidos')->counts('partidos')
                    ->description(fn (Fase $record): ?string => $record->estado === EstadoFase::Pendiente ? null : $record->partidos()->whereIn('estado', ['jugado', 'walkover'])->count().' jugados'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Añadir fase')
                    ->modalHeading('Nueva fase')
                    ->using(function (array $data, Action $action): Model {
                        $limpio = self::comprobar($data, $action);
                        $this->ajustarQuienPasa($data, null, $action);

                        return $this->getRelationship()->create($limpio);
                    }),
            ])
            ->recordActions([
                SorteoDeGrupos::accion(),
                Action::make('quienPasa')
                    ->label('Quién pasa')
                    ->icon(Heroicon::OutlinedArrowRightCircle)
                    ->color('gray')
                    // Antes del sorteo se cambia en «Editar»; después, aquí, hasta cerrar la fase.
                    ->visible(fn (Fase $record): bool => $record->tipo === TipoDeFase::Grupos && ! in_array($record->estado, [EstadoFase::Pendiente, EstadoFase::Cerrada], true))
                    ->modalHeading(fn (Fase $record): string => 'Quién pasa de «'.$record->nombre.'»')
                    ->modalDescription('Se aplica al cerrar la fase: pasan los primeros de cada grupo que digas y, si quieres, los mejores de la posición siguiente (por ejemplo, los 2 mejores terceros).')
                    ->modalSubmitActionLabel('Guardar')
                    ->fillForm(fn (Fase $record): array => [
                        'por_grupo' => $record->configuracionDeGrupos()->clasificados->porGrupo,
                        'cuantos_mejores' => $record->configuracionDeGrupos()->clasificados->cuantosMejores,
                        'mejores_de_posicion' => $record->configuracionDeGrupos()->clasificados->mejoresDePosicion ?? $record->configuracionDeGrupos()->clasificados->porGrupo + 1,
                    ])
                    ->schema([
                        TextInput::make('por_grupo')->label('Pasan por grupo')->integer()->minValue(0)->required(),
                        TextInput::make('cuantos_mejores')->label('Y además, los mejores…')->integer()->minValue(0)->default(0)->live()
                            ->helperText('Cuántos: por ejemplo, 2 para los 2 mejores terceros. 0, ninguno.'),
                        TextInput::make('mejores_de_posicion')->label('… de qué posición')->integer()->minValue(1)
                            ->visible(fn (Get $get): bool => (int) $get('cuantos_mejores') > 0)
                            ->helperText('3 para los terceros.'),
                    ])
                    ->action(function (Fase $record, array $data, Action $action): void {
                        $cuantos = (int) ($data['cuantos_mejores'] ?? 0);
                        $clasificados = [
                            'por_grupo' => (int) $data['por_grupo'],
                            'cuantos_mejores' => $cuantos,
                            'mejores_de_posicion' => $cuantos > 0 ? (int) ($data['mejores_de_posicion'] ?? 0) : null,
                        ];

                        try {
                            $configuracion = ConfiguracionDeGrupos::desdeArray([...$record->configuracion, 'clasificados' => array_filter($clasificados, fn (mixed $v): bool => $v !== null)]);
                        } catch (ConfiguracionInvalida $e) {
                            Avisos::error('No se ha guardado', $e->errores);
                            $action->halt();

                            return;
                        }

                        $record->update(['configuracion' => $configuracion->toArray()]);
                        $regla = $configuracion->clasificados;
                        Avisos::hecho('Guardado', 'Pasan '.$regla->porGrupo.' por grupo'.($regla->cuantosMejores > 0 ? ' y los '.$regla->cuantosMejores.' mejores '.$regla->mejoresDePosicion.'º' : '').'. Revisa el cuadro: «Editar» en su fila.');
                    }),
                Action::make('cerrar')
                    ->label('Cerrar fase')
                    ->icon(Heroicon::OutlinedLockClosed)
                    ->color('success')
                    ->visible(fn (Fase $record): bool => in_array($record->estado, [EstadoFase::Preparada, EstadoFase::EnJuego], true))
                    ->requiresConfirmation()
                    ->modalDescription(fn (Fase $record): string => $record->tipo->esDeGrupos()
                        ? 'Hace falta que todos los partidos tengan resultado. Se deciden los clasificados y, si la fase siguiente es un cuadro, se construye con ellos.'
                        : 'Hace falta que todos los partidos tengan resultado. La fase queda cerrada.')
                    ->schema(fn (Fase $record): array => $record->tipo->esDeGrupos() ? [
                        Toggle::make('avisar')->label('Avisar a cada pareja clasificada de su cruce')->default(false)
                            ->helperText('Por correo y en su portal. Desmarcado, no se avisa a nadie.'),
                    ] : [])
                    ->action(function (Fase $record, array $data, Action $action): void {
                        try {
                            $clasificados = app(CerrarFase::class)->handle($record, $this->usuario(), avisar: (bool) ($data['avisar'] ?? false));
                        } catch (ErrorDeCompeticion $e) {
                            Avisos::error('No se ha podido cerrar la fase', [$e->getMessage()]);
                            $action->halt();

                            return;
                        }

                        Avisos::hecho('Fase cerrada', $clasificados === [] ? null : count($clasificados).' clasificados pasan a la fase siguiente.');
                    }),
                ActionGroup::make([
                    Action::make('clasificacion')
                        ->label('Clasificación')
                        ->icon(Heroicon::OutlinedListBullet)
                        ->visible(fn (Fase $record): bool => $record->tipo->esDeGrupos() && $record->estado !== EstadoFase::Pendiente)
                        ->modalHeading(fn (Fase $record): string => 'Clasificación · '.$record->nombre)
                        ->modalContent(fn (Fase $record) => view('filament.panel.competicion.clasificacion', ['fase' => $record]))
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Cerrar')
                        ->modalWidth('4xl'),
                    Action::make('cuadro')
                        ->label('Cuadro')
                        ->icon(Heroicon::OutlinedSquare3Stack3d)
                        ->visible(fn (Fase $record): bool => $record->tipo->esDeCuadro() && $record->estado !== EstadoFase::Pendiente)
                        ->modalHeading(fn (Fase $record): string => 'Cuadro · '.$record->nombre)
                        ->modalContent(fn (Fase $record) => view('filament.panel.competicion.cuadro', ['fase' => $record]))
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Cerrar')
                        ->modalWidth('7xl'),
                    Action::make('partidos')
                        ->label('Partidos')
                        ->icon(Heroicon::OutlinedTableCells)
                        ->visible(fn (Fase $record): bool => $record->estado !== EstadoFase::Pendiente)
                        ->url(fn (): string => PartidosDelTorneo::getUrl(['record' => $this->categoria()->torneo_id, 'tableFilters' => ['categoria' => ['value' => $this->categoria()->id]]])),
                ]),
                EditAction::make()
                    ->visible(fn (Fase $record): bool => $record->estado === EstadoFase::Pendiente)
                    ->mutateRecordDataUsing(fn (array $data): array => FaseForm::alRellenar($data))
                    ->using(function (Fase $record, array $data, Action $action): Fase {
                        $limpio = self::comprobar($data, $action);
                        $this->ajustarQuienPasa($data, $record, $action);
                        $record->update($limpio);

                        return $record;
                    }),
                DeleteAction::make()
                    ->visible(fn (Fase $record): bool => $record->estado === EstadoFase::Pendiente),
            ]);
    }

    private function usuario(): User
    {
        $usuario = Filament::auth()->user();
        assert($usuario instanceof User);

        return $usuario;
    }

    private function categoria(): Categoria
    {
        $categoria = $this->getOwnerRecord();
        assert($categoria instanceof Categoria);

        return $categoria;
    }

    /**
     * Con un cuadro hecho a mano por posiciones, la fase de grupos de la
     * que sale pasa a clasificar justo a los que están en sus cruces (los N
     * primeros de cada grupo y los mejores de una posición).
     *
     * @param  array<string, mixed>  $data
     */
    private function ajustarQuienPasa(array $data, ?Fase $fase, Action $action): void
    {
        $tipo = $data['tipo'] ?? null;
        $configuracion = (array) ($data['configuracion'] ?? []);
        if (($tipo instanceof TipoDeFase ? $tipo->value : $tipo) !== TipoDeFase::Eliminatoria->value || ($configuracion['regla_de_cruces'] ?? null) !== ReglaDeCruces::APosiciones->value) {
            return;
        }

        $categoria = $this->categoria();
        $grupos = FaseForm::faseDeGrupos($categoria, $fase);
        if ($grupos === null) {
            return;
        }

        try {
            $quien = FaseForm::quienPasaDesde(
                FaseForm::codigosDeLosCruces((array) ($configuracion['cruces_por_posicion'] ?? [])),
                FaseForm::letrasYTamano($categoria, $grupos)[0],
                is_numeric($configuracion['mejores_de'] ?? null) ? (int) $configuracion['mejores_de'] : null,
            );
            $actual = $grupos->configuracionDeGrupos()->clasificados;
            if ($actual->porGrupo === $quien['por_grupo'] && $actual->cuantosMejores === $quien['cuantos_mejores'] && ($quien['cuantos_mejores'] === 0 || $actual->mejoresDePosicion === $quien['mejores_de_posicion'])) {
                return;
            }
            if ($grupos->estado === EstadoFase::Cerrada) {
                throw new ConfiguracionInvalida(['La fase de grupos ya está cerrada: no se puede cambiar quién pasa.']);
            }
            $nueva = ConfiguracionDeGrupos::desdeArray([...$grupos->configuracion, 'clasificados' => array_filter($quien, fn (mixed $v): bool => $v !== null)]);
        } catch (ConfiguracionInvalida $e) {
            Avisos::error('Los cruces no valen', $e->errores);
            $action->halt();

            return;
        }

        $grupos->update(['configuracion' => $nueva->toArray()]);
        Avisos::hecho('Cuadro guardado', '«'.$grupos->nombre.'» pasa a clasificar '.$quien['por_grupo'].' por grupo'.($quien['cuantos_mejores'] > 0 ? ' y los '.$quien['cuantos_mejores'].' mejores '.$quien['mejores_de_posicion'].'º' : '').'.');
    }

    /**
     * El DTO del motor valida; si no pasa, el aviso enseña sus frases y el
     * formulario sigue abierto.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function comprobar(array $data, Action $action): array
    {
        try {
            return FaseForm::normalizar($data);
        } catch (ConfiguracionInvalida $e) {
            Avisos::error('La configuración de la fase no es válida', $e->errores);
            $action->halt();
        }

        return $data;
    }
}
