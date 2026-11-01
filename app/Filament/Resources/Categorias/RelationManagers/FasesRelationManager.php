<?php

namespace App\Filament\Resources\Categorias\RelationManagers;

use App\Actions\Competicion\CerrarFase;
use App\Actions\Competicion\GenerarPartidos;
use App\Actions\Competicion\SortearGrupos;
use App\Competicion\Errores\ConfiguracionInvalida;
use App\Competicion\Errores\ErrorDeCompeticion;
use App\Enums\EstadoFase;
use App\Enums\TipoDeFase;
use App\Filament\Avisos;
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
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
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
                TextColumn::make('partidos_count')->label('Partidos')->counts('partidos')
                    ->description(fn (Fase $record): ?string => $record->estado === EstadoFase::Pendiente ? null : $record->partidos()->whereIn('estado', ['jugado', 'walkover'])->count().' jugados'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Añadir fase')
                    ->modalHeading('Nueva fase')
                    ->using(fn (array $data, Action $action): Model => $this->getRelationship()->create(self::comprobar($data, $action))),
            ])
            ->recordActions([
                Action::make('sortear')
                    ->label(fn (Fase $record): string => $record->estado === EstadoFase::Pendiente ? 'Sortear' : 'Volver a sortear')
                    ->icon(Heroicon::OutlinedArrowPathRoundedSquare)
                    ->color(fn (Fase $record): string => $record->estado === EstadoFase::Pendiente ? 'primary' : 'gray')
                    ->visible(fn (Fase $record): bool => $record->tipo->esDeGrupos() && $record->estado !== EstadoFase::Cerrada)
                    ->requiresConfirmation()
                    ->modalHeading(fn (Fase $record): string => 'Sortear «'.$record->nombre.'»')
                    ->modalDescription(fn (Fase $record): string => $record->tieneResultados()
                        ? 'La fase ya tiene resultados: volver a sortear los borra todos y rehace los grupos y los partidos.'
                        : 'Se reparten en grupos las parejas confirmadas de la categoría y se generan sus partidos.')
                    ->modalSubmitActionLabel('Sortear')
                    ->action(function (Fase $record, Action $action): void {
                        try {
                            $reparto = app(SortearGrupos::class)->handle($record, $this->usuario(), forzar: $record->tieneResultados());
                            $partidos = app(GenerarPartidos::class)->handle($record->refresh(), $this->usuario());
                        } catch (ErrorDeCompeticion $e) {
                            Avisos::error('No se ha podido sortear', [$e->getMessage()]);
                            $action->halt();

                            return;
                        }

                        Avisos::hecho(count($reparto->grupos).' grupos y '.$partidos.' partidos', $reparto->avisos === [] ? null : implode(' ', $reparto->avisos));
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
                    ->action(function (Fase $record, Action $action): void {
                        try {
                            $clasificados = app(CerrarFase::class)->handle($record, $this->usuario());
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
                        $record->update(self::comprobar($data, $action));

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
