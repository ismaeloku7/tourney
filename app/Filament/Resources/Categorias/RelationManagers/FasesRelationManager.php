<?php

namespace App\Filament\Resources\Categorias\RelationManagers;

use App\Actions\Competicion\CerrarFase;
use App\Actions\Competicion\GenerarPartidos;
use App\Actions\Competicion\SortearGrupos;
use App\Actions\Inscripciones\CambiarEstadoDeInscripcion;
use App\Actions\Inscripciones\FijarCabezaDeSerie;
use App\Competicion\Datos\Grupo;
use App\Competicion\Datos\RepartoDeGrupos;
use App\Competicion\Errores\ConfiguracionInvalida;
use App\Competicion\Errores\ErrorDeCompeticion;
use App\Competicion\Errores\OperacionNoPermitida;
use App\Enums\EstadoFase;
use App\Enums\EstadoInscripcion;
use App\Enums\EstadoParticipante;
use App\Enums\ModoDeSorteo;
use App\Enums\TipoDeFase;
use App\Filament\Avisos;
use App\Filament\Resources\Categorias\Schemas\FaseForm;
use App\Filament\Resources\Torneos\Pages\PartidosDelTorneo;
use App\Models\Categoria;
use App\Models\Fase;
use App\Models\Inscripcion;
use App\Models\Jugador;
use App\Models\Participante;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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
                    ->using(fn (array $data, Action $action): Model => $this->getRelationship()->create(self::comprobar($data, $action))),
            ])
            ->recordActions([
                Action::make('sortear')
                    ->label(fn (Fase $record): string => $record->estado === EstadoFase::Pendiente ? 'Sortear' : 'Volver a sortear')
                    ->icon(Heroicon::OutlinedArrowPathRoundedSquare)
                    ->color(fn (Fase $record): string => $record->estado === EstadoFase::Pendiente ? 'primary' : 'gray')
                    ->visible(fn (Fase $record): bool => $record->tipo->esDeGrupos() && $record->estado !== EstadoFase::Cerrada)
                    ->modalHeading(fn (Fase $record): string => 'Sortear «'.$record->nombre.'»')
                    ->modalDescription(fn (Fase $record): string => $this->descripcionDelSorteo($record))
                    ->modalSubmitActionLabel('Sortear')
                    ->modalWidth('3xl')
                    ->fillForm(fn (Fase $record): array => $this->datosDelSorteo($record))
                    ->schema(fn (Fase $record): array => $this->formularioDelSorteo($record))
                    ->action(function (Fase $record, array $data, Action $action): void {
                        try {
                            [$reparto, $partidos] = DB::transaction(fn (): array => $this->sortear($record, $data));
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
                        $record->update(self::comprobar($data, $action));

                        return $record;
                    }),
                DeleteAction::make()
                    ->visible(fn (Fase $record): bool => $record->estado === EstadoFase::Pendiente),
            ]);
    }

    private function descripcionDelSorteo(Fase $fase): string
    {
        $texto = $fase->tieneResultados()
            ? 'La fase ya tiene resultados: volver a sortear los borra todos y rehace los grupos y los partidos.'
            : 'Entran las parejas confirmadas de la categoría y se generan sus partidos.';

        $pendientes = $this->parejasDelSorteo()->filter(fn (Participante $p): bool => $p->inscripcion?->estado === EstadoInscripcion::Pendiente)->count();
        if ($pendientes > 0) {
            $texto .= " Hay {$pendientes} pendientes: solo entran las que pongas en un grupo o como cabeza de serie, y se confirman al sortear.";
        }

        return $texto;
    }

    /**
     * Las parejas que se pueden sortear: confirmadas y pendientes, con sus
     * jugadores para buscarlas por nombre y nivel.
     *
     * @return Collection<int, Participante>
     */
    private function parejasDelSorteo(): Collection
    {
        return $this->categoria()->participantes()
            ->where('estado', EstadoParticipante::Activo->value)
            ->whereHas('inscripcion', fn (Builder $q) => $q->whereIn('estado', [EstadoInscripcion::Confirmada->value, EstadoInscripcion::Pendiente->value]))
            ->with(['inscripcion', 'jugadores'])
            ->orderBy('nombre')
            ->get();
    }

    /** @return array<int, string> */
    private function opcionesDelSorteo(): array
    {
        return $this->parejasDelSorteo()->mapWithKeys(fn (Participante $p): array => [$p->id => self::etiquetaDelSorteo($p)])->all();
    }

    /**
     * «Gil / Sanz · Ana Gil (3,5) / Eva Sanz (4) · Media 3,75 · CS 1»: lo
     * que sale y se busca en los selectores del sorteo.
     */
    public static function etiquetaDelSorteo(Participante $pareja): string
    {
        $jugadores = $pareja->jugadores
            ->map(fn (Jugador $j): string => trim($j->nombre.' '.$j->apellidos).' ('.($j->nivel_declarado !== null ? str_replace('.', ',', $j->nivel_declarado) : 'sin nivel').')')
            ->implode(' / ');

        return implode(' · ', array_filter([
            $pareja->nombre,
            $jugadores !== '' ? $jugadores : null,
            $pareja->nivelMedioTexto(),
            $pareja->cabeza_de_serie !== null ? "CS {$pareja->cabeza_de_serie}" : null,
            $pareja->inscripcion?->estado === EstadoInscripcion::Pendiente ? 'pendiente' : null,
        ]));
    }

    /** @return array<string, mixed> */
    private function datosDelSorteo(Fase $fase): array
    {
        $configuracion = $fase->configuracionDeGrupos();
        $datos = [
            'modo' => $configuracion->modoDeSorteo->value,
            'cabezas' => $this->categoria()->participantes()->whereNotNull('cabeza_de_serie')->orderBy('cabeza_de_serie')->get()->map(fn (Participante $p): string => (string) $p->id)->all(),
        ];

        foreach ($configuracion->gruposManuales ?? [] as $i => $ids) {
            $datos["grupo_{$i}"] = array_map(fn (int $id): string => (string) $id, $ids);
        }

        return $datos;
    }

    /**
     * El modo de sorteo, las cabezas de serie en orden y un selector por
     * grupo (A, B…) para fijar parejas a mano antes de sortear el resto.
     *
     * @return list<Component>
     */
    private function formularioDelSorteo(Fase $fase): array
    {
        $opciones = $this->opcionesDelSorteo();
        $configuracion = $fase->configuracionDeGrupos();
        $n = max(1, count($opciones));
        $tamano = $configuracion->tamanoDeGrupo ?? 4;
        $alSorteo = $configuracion->numeroDeGrupos !== null ? min($configuracion->numeroDeGrupos, $n) : max(1, intdiv($n, $tamano));
        $aMano = $configuracion->numeroDeGrupos ?? max(1, (int) ceil($n / $tamano));
        $esManual = fn (Get $get): bool => $get('modo') === ModoDeSorteo::Manual->value;

        $campos = [];
        if ($fase->tipo === TipoDeFase::Grupos) {
            $campos[] = Radio::make('modo')->label('Cómo se hace')->inline()->live()->required()
                ->options([
                    ModoDeSorteo::Aleatorio->value => 'Al azar',
                    ModoDeSorteo::CabezasDeSerie->value => 'Con cabezas de serie',
                    ModoDeSorteo::Manual->value => 'Todo a mano',
                ]);
            $campos[] = Select::make('cabezas')->label('Cabezas de serie, en orden')
                ->multiple()->searchable()->optionsLimit(1000)->options($opciones)
                ->visible(fn (Get $get): bool => $get('modo') === ModoDeSorteo::CabezasDeSerie->value)
                ->helperText('La primera que elijas es la 1, la segunda la 2… Cada una encabeza un grupo y se reparten en serpiente; también cuentan para el cuadro.');
        }

        for ($i = 0; $i < max($alSorteo, $aMano); $i++) {
            $campos[] = Select::make("grupo_{$i}")->label('Grupo '.Grupo::nombrePorOrden($i + 1))
                ->multiple()->searchable()->optionsLimit(1000)->options($opciones)
                ->visible(fn (Get $get): bool => $i < ($esManual($get) ? $aMano : $alSorteo))
                ->placeholder(fn (Get $get): string => $esManual($get) ? 'Elige sus parejas' : 'Al sorteo')
                ->helperText($i === 0 ? 'Busca por pareja, jugador o nivel. Lo que dejes en blanco se sortea (en «Todo a mano», cada pareja va donde la pongas).' : null);
        }

        return $campos;
    }

    /**
     * Confirma las pendientes elegidas, guarda el modo y las cabezas de
     * serie, sortea y genera los partidos.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: RepartoDeGrupos, 1: int}
     */
    private function sortear(Fase $fase, array $data): array
    {
        $usuario = $this->usuario();
        $configuracion = $fase->configuracionDeGrupos();
        $modo = $fase->tipo === TipoDeFase::Grupos ? (ModoDeSorteo::tryFrom((string) ($data['modo'] ?? '')) ?? $configuracion->modoDeSorteo) : $configuracion->modoDeSorteo;
        $nombres = $this->parejasDelSorteo()->pluck('nombre', 'id');
        $ids = fn (mixed $valor): array => array_values(array_unique(array_map(fn (mixed $id): int => (int) $id, is_array($valor) ? $valor : [])));

        $fijos = [];
        for ($i = 0; array_key_exists("grupo_{$i}", $data); $i++) {
            $fijos[] = $ids($data["grupo_{$i}"]);
        }
        while ($fijos !== [] && end($fijos) === []) {
            array_pop($fijos);
        }

        $vistos = [];
        foreach ($fijos as $miembros) {
            foreach ($miembros as $id) {
                if (isset($vistos[$id])) {
                    throw new OperacionNoPermitida('«'.$nombres->get($id, (string) $id).'» está en dos grupos.');
                }
                $vistos[$id] = true;
            }
        }

        $cabezas = $modo === ModoDeSorteo::CabezasDeSerie ? $ids($data['cabezas'] ?? []) : null;

        // Las pendientes que se ponen en un grupo o como cabeza de serie entran: se confirman.
        $elegidas = array_unique([...array_keys($vistos), ...($cabezas ?? [])]);
        $pendientes = Inscripcion::query()->whereIn('participante_id', $elegidas)->where('estado', EstadoInscripcion::Pendiente->value)->get();
        foreach ($pendientes as $inscripcion) {
            app(CambiarEstadoDeInscripcion::class)->handle($inscripcion, EstadoInscripcion::Confirmada, $usuario, avisar: false);
        }

        if ($cabezas !== null) {
            $categoria = $this->categoria();
            // Primero se quitan las que cambian, para que ningún número choque.
            foreach ($categoria->participantes()->whereNotNull('cabeza_de_serie')->get() as $participante) {
                $posicion = array_search($participante->id, $cabezas, true);
                if ($posicion === false || $posicion + 1 !== $participante->cabeza_de_serie) {
                    app(FijarCabezaDeSerie::class)->handle($participante, null, $usuario);
                }
            }
            foreach ($cabezas as $k => $id) {
                $participante = $categoria->participantes()->findOrFail($id);
                if ($participante->cabeza_de_serie !== $k + 1) {
                    app(FijarCabezaDeSerie::class)->handle($participante, $k + 1, $usuario);
                }
            }
        }

        if ($modo !== $configuracion->modoDeSorteo) {
            $fase->update(['configuracion' => [...$fase->configuracion, 'modo_de_sorteo' => $modo->value, 'grupos_manuales' => $fijos]]);
        }

        $reparto = app(SortearGrupos::class)->handle($fase->refresh(), $usuario, forzar: $fase->tieneResultados(), fijos: $fijos);
        $partidos = app(GenerarPartidos::class)->handle($fase->refresh(), $usuario);

        return [$reparto, $partidos];
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
