<?php

namespace App\Filament\Competicion;

use App\Actions\Competicion\EditarGrupos;
use App\Actions\Competicion\GenerarPartidos;
use App\Actions\Competicion\SortearGrupos;
use App\Actions\Inscripciones\CambiarEstadoDeInscripcion;
use App\Actions\Inscripciones\FijarCabezaDeSerie;
use App\Competicion\Datos\ConfiguracionDeGrupos;
use App\Competicion\Datos\Grupo;
use App\Competicion\Datos\Participante as ParticipanteDto;
use App\Competicion\Datos\RepartoDeGrupos;
use App\Competicion\Errores\ErrorDeCompeticion;
use App\Competicion\Errores\OperacionNoPermitida;
use App\Competicion\RepartidorDeGrupos;
use App\Enums\EstadoFase;
use App\Enums\EstadoInscripcion;
use App\Enums\EstadoParticipante;
use App\Enums\ModoDeSorteo;
use App\Enums\TipoDeFase;
use App\Filament\Avisos;
use App\Models\Fase;
use App\Models\Inscripcion;
use App\Models\Jugador;
use App\Models\Participante;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * El diálogo de los grupos de una fase: cómo se sortea, las cabezas de
 * serie en orden y un selector por grupo. Lo que se deja en blanco se
 * sortea al guardar (o antes, con «Sortear los huecos», para retocarlo).
 * Con todo colocado, se guardan los grupos tal cual, también después del
 * sorteo: solo se rehacen los partidos de los grupos que cambian.
 */
final class SorteoDeGrupos
{
    public static function accion(): Action
    {
        return Action::make('sortear')
            ->label(fn (Fase $record): string => $record->grupos()->exists() ? 'Editar grupos' : 'Sortear')
            ->icon(Heroicon::OutlinedArrowPathRoundedSquare)
            ->color(fn (Fase $record): string => $record->grupos()->exists() ? 'gray' : 'primary')
            ->visible(fn (Fase $record): bool => $record->tipo->esDeGrupos() && $record->estado !== EstadoFase::Cerrada)
            ->modalHeading(fn (Fase $record): string => ($record->grupos()->exists() ? 'Grupos de «' : 'Sortear «').$record->nombre.'»')
            ->modalDescription(fn (Fase $record): string => self::descripcion($record))
            ->modalSubmitActionLabel('Guardar')
            ->modalWidth('4xl')
            ->fillForm(fn (Fase $record): array => self::datos($record))
            ->schema(fn (Fase $record): array => self::formulario($record))
            ->action(function (Fase $record, array $data, Action $action): void {
                try {
                    [$titulo, $cuerpo] = DB::transaction(fn (): array => self::guardar($record, $data));
                } catch (ErrorDeCompeticion $e) {
                    Avisos::error('No se ha podido sortear', [$e->getMessage()]);
                    $action->halt();

                    return;
                }

                Avisos::hecho($titulo, $cuerpo);
            });
    }

    /**
     * «Gil / Sanz · Ana Gil (3,5) / Eva Sanz (4) · Media 3,75 · CS 1»: lo
     * que sale y se busca en los selectores.
     */
    public static function etiqueta(Participante $pareja): string
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

    private static function descripcion(Fase $fase): string
    {
        $texto = $fase->grupos()->exists()
            ? 'Cambia lo que quieras. Solo se rehacen los partidos de los grupos que cambian (y se pierden sus resultados y horarios); los demás quedan como están. Si dejas huecos, se vuelve a sortear todo lo que no esté colocado.'
            : 'Coloca las parejas que quieras; los huecos se sortean al guardar, o antes con «Sortear los huecos» para retocarlos.';

        $pendientes = self::parejas($fase)->filter(fn (Participante $p): bool => $p->inscripcion?->estado === EstadoInscripcion::Pendiente)->count();
        if ($pendientes > 0) {
            $texto .= " Hay {$pendientes} pendientes: solo entran las que pongas en un grupo o como cabeza de serie, y se confirman al guardar.";
        }

        return $texto;
    }

    /**
     * Las parejas que pueden entrar: confirmadas y pendientes.
     *
     * @return Collection<int, Participante>
     */
    private static function parejas(Fase $fase): Collection
    {
        return $fase->categoria->participantes()
            ->where('estado', EstadoParticipante::Activo->value)
            ->whereHas('inscripcion', fn (Builder $q) => $q->whereIn('estado', [EstadoInscripcion::Confirmada->value, EstadoInscripcion::Pendiente->value]))
            ->with(['inscripcion', 'jugadores', 'participanteJugadores'])
            ->orderBy('nombre')
            ->get();
    }

    /** @return array<string, mixed> */
    private static function datos(Fase $fase): array
    {
        $configuracion = $fase->configuracionDeGrupos();
        $datos = [
            'modo' => $configuracion->modoDeSorteo->value,
            'cabezas' => $fase->categoria->participantes()->whereNotNull('cabeza_de_serie')->orderBy('cabeza_de_serie')->get()
                ->map(fn (Participante $p): string => (string) $p->id)->all(),
        ];

        $grupos = $fase->grupos()->with('participantes')->get();
        if ($grupos->isNotEmpty()) {
            // Las que ya no pueden entrar (de baja, retiradas) no se ofrecen: al guardar salen de su grupo.
            $validas = self::parejas($fase)->modelKeys();
            foreach ($grupos->values() as $i => $grupo) {
                $datos["grupo_{$i}"] = $grupo->participantes
                    ->filter(fn (Participante $p): bool => in_array($p->id, $validas, true))
                    ->map(fn (Participante $p): string => (string) $p->id)->values()->all();
            }
        } else {
            foreach ($configuracion->gruposManuales ?? [] as $i => $ids) {
                $datos["grupo_{$i}"] = array_map(fn (int $id): string => (string) $id, $ids);
            }
            $datos['repartidos'] = array_map(fn (int $id): string => (string) $id, $configuracion->repartidos);
        }

        return $datos;
    }

    /** @return list<Component> */
    private static function formulario(Fase $fase): array
    {
        $opciones = self::parejas($fase)->mapWithKeys(fn (Participante $p): array => [$p->id => self::etiqueta($p)])->all();
        $total = self::cuantosSelectores($fase, count($opciones));
        [$alSorteo] = self::grupos($fase->configuracionDeGrupos(), count($opciones));
        $alSorteo = max($alSorteo, $fase->grupos()->count());
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

        $campos[] = Select::make('repartidos')->label('Repartir al azar entre los grupos')
            ->multiple()->searchable()->optionsLimit(1000)->options($opciones)
            ->placeholder('Ninguna')
            ->helperText('Estas parejas caen en grupos al azar, una por grupo (si hay más que grupos, lo más repartidas posible). Por ejemplo, para separar a las más fuertes sin numerarlas.');

        $campos[] = Actions::make([
            Action::make('huecos')->label('Sortear los huecos')->icon(Heroicon::OutlinedSparkles)
                ->action(fn (Get $get, Set $set) => self::sortearHuecos($fase, $get, $set, $total)),
            Action::make('vaciar')->label('Vaciar los grupos')->icon(Heroicon::OutlinedTrash)->color('gray')
                ->action(function (Set $set) use ($total): void {
                    for ($i = 0; $i < $total; $i++) {
                        $set("grupo_{$i}", []);
                    }
                }),
        ]);

        for ($i = 0; $i < $total; $i++) {
            $campos[] = Select::make("grupo_{$i}")->label('Grupo '.Grupo::nombrePorOrden($i + 1))
                ->multiple()->searchable()->optionsLimit(1000)->live()
                ->options(fn (Get $get): array => array_diff_key($opciones, array_flip(self::enOtrosGrupos($get, $i, $total))))
                ->visible(fn (Get $get): bool => $i < ($esManual($get) ? $total : $alSorteo))
                ->placeholder(fn (Get $get): string => $esManual($get) ? 'Elige sus parejas' : 'Al sorteo')
                ->helperText($i === 0 ? 'Busca por pareja, jugador o nivel. Una pareja colocada ya no sale en los demás grupos.' : null);
        }

        return $campos;
    }

    /**
     * Cuántos grupos salen sorteando y cuántos haciéndolo a mano.
     *
     * @return array{0: int, 1: int}
     */
    private static function grupos(ConfiguracionDeGrupos $configuracion, int $parejas): array
    {
        $n = max(1, $parejas);
        $tamano = $configuracion->tamanoDeGrupo ?? 4;

        return [
            $configuracion->numeroDeGrupos !== null ? min($configuracion->numeroDeGrupos, $n) : max(1, intdiv($n, $tamano)),
            $configuracion->numeroDeGrupos ?? max(1, (int) ceil($n / $tamano)),
        ];
    }

    private static function cuantosSelectores(Fase $fase, int $parejas): int
    {
        [$alSorteo, $aMano] = self::grupos($fase->configuracionDeGrupos(), $parejas);

        return max($alSorteo, $aMano, $fase->grupos()->count());
    }

    /** @return list<string> */
    private static function enOtrosGrupos(Get $get, int $grupo, int $total): array
    {
        $ids = [];
        for ($i = 0; $i < $total; $i++) {
            if ($i !== $grupo) {
                $ids = [...$ids, ...array_values(array_map(fn (mixed $id): string => (string) $id, (array) ($get("grupo_{$i}") ?? [])))];
            }
        }

        return $ids;
    }

    /**
     * Rellena en el formulario los huecos con un sorteo, respetando lo ya
     * colocado (y las cabezas de serie, si las hay). No guarda nada.
     */
    private static function sortearHuecos(Fase $fase, Get $get, Set $set, int $total): void
    {
        $fijos = [];
        for ($i = 0; $i < $total; $i++) {
            $fijos[] = self::ids($get("grupo_{$i}"));
        }
        while ($fijos !== [] && end($fijos) === []) {
            array_pop($fijos);
        }

        $modo = ModoDeSorteo::tryFrom((string) $get('modo')) ?? $fase->configuracionDeGrupos()->modoDeSorteo;

        try {
            $reparto = self::repartir($fase, $fijos, $modo, $modo === ModoDeSorteo::CabezasDeSerie ? self::ids($get('cabezas')) : [], self::ids($get('repartidos')));
        } catch (ErrorDeCompeticion $e) {
            Avisos::error('No se han podido sortear los huecos', [$e->getMessage()]);

            return;
        }

        for ($i = 0; $i < $total; $i++) {
            $set("grupo_{$i}", array_map(fn (int $id): string => (string) $id, $reparto->grupos[$i]->participantes ?? []));
        }
        // Ya están en sus grupos: a partir de aquí se retocan a mano.
        $set('repartidos', []);

        Avisos::hecho('Huecos sorteados', 'Revisa y cambia lo que quieras: no se guarda hasta pulsar «Guardar».');
    }

    /**
     * Un sorteo de lo que falta por colocar entre las confirmadas (y las
     * pendientes ya elegidas), respetando lo fijado, con cabezas de serie
     * su orden, y repartiendo al azar entre los grupos las elegidas para
     * ello.
     *
     * @param  list<list<int>>  $fijos
     * @param  list<int>  $cabezas
     * @param  list<int>  $repartidos
     */
    private static function repartir(Fase $fase, array $fijos, ModoDeSorteo $modo, array $cabezas, array $repartidos = []): RepartoDeGrupos
    {
        $elegidas = [...array_merge(...$fijos), ...$cabezas, ...$repartidos];

        $participantes = array_values(self::parejas($fase)
            ->filter(fn (Participante $p): bool => $p->inscripcion?->estado === EstadoInscripcion::Confirmada || in_array($p->id, $elegidas, true))
            ->sortBy('id')
            ->map(function (Participante $p) use ($cabezas): ParticipanteDto {
                $posicion = array_search($p->id, $cabezas, true);

                return new ParticipanteDto($p->id, $p->nombre, $posicion === false ? null : $posicion + 1, $p->idsDeJugadores());
            })
            ->all());

        return (new RepartidorDeGrupos)->repartir($participantes, ConfiguracionDeGrupos::desdeArray([
            ...$fase->configuracion,
            'modo_de_sorteo' => ($modo === ModoDeSorteo::Manual ? ModoDeSorteo::Aleatorio : $modo)->value,
            'semilla' => null,
            'grupos_manuales' => $fijos,
            'repartidos' => $repartidos,
        ]));
    }

    /**
     * Confirma las pendientes elegidas, guarda el modo y las cabezas de
     * serie y, con todo colocado (o en «Todo a mano»), guarda los grupos
     * tal cual; si quedan huecos, sortea.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: string, 1: string|null}
     */
    private static function guardar(Fase $fase, array $data): array
    {
        $usuario = Filament::auth()->user();
        assert($usuario instanceof User);
        $configuracion = $fase->configuracionDeGrupos();
        $modo = $fase->tipo === TipoDeFase::Grupos ? (ModoDeSorteo::tryFrom((string) ($data['modo'] ?? '')) ?? $configuracion->modoDeSorteo) : $configuracion->modoDeSorteo;
        $nombres = self::parejas($fase)->pluck('nombre', 'id');

        $fijos = [];
        for ($i = 0; array_key_exists("grupo_{$i}", $data); $i++) {
            $fijos[] = self::ids($data["grupo_{$i}"]);
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

        $cabezas = $modo === ModoDeSorteo::CabezasDeSerie ? self::ids($data['cabezas'] ?? []) : null;
        $repartidos = self::ids($data['repartidos'] ?? []);

        // Las pendientes que se ponen en un grupo, como cabeza de serie o para repartir entran: se confirman.
        $elegidas = array_unique([...array_keys($vistos), ...($cabezas ?? []), ...$repartidos]);
        foreach (Inscripcion::query()->whereIn('participante_id', $elegidas)->where('estado', EstadoInscripcion::Pendiente->value)->get() as $inscripcion) {
            app(CambiarEstadoDeInscripcion::class)->handle($inscripcion, EstadoInscripcion::Confirmada, $usuario, avisar: false);
        }

        if ($cabezas !== null) {
            self::guardarCabezas($fase, $cabezas, $usuario);
        }

        if ($modo !== $configuracion->modoDeSorteo) {
            $fase->update(['configuracion' => [...$fase->configuracion, 'modo_de_sorteo' => $modo->value, 'grupos_manuales' => $fijos]]);
        }

        $entran = $fase->categoria->participantes()
            ->where('estado', EstadoParticipante::Activo->value)
            ->whereHas('inscripcion', fn (Builder $q) => $q->where('estado', EstadoInscripcion::Confirmada->value))
            ->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
        $faltan = array_diff($entran, array_keys($vistos));

        // Sin grupos todavía y con huecos: el sorteo de siempre (con su semilla).
        if ($faltan !== [] && $modo !== ModoDeSorteo::Manual && $fase->grupos()->doesntExist()) {
            $reparto = app(SortearGrupos::class)->handle($fase->refresh(), $usuario, fijos: $fijos, repartidos: $repartidos);
            $partidos = app(GenerarPartidos::class)->handle($fase->refresh(), $usuario);

            return [count($reparto->grupos).' grupos y '.$partidos.' partidos', $reparto->avisos === [] ? null : implode(' ', $reparto->avisos)];
        }

        // Con grupos ya hechos, los huecos se sortean y solo se rehacen los grupos que cambian.
        if ($faltan !== [] && $modo !== ModoDeSorteo::Manual) {
            $fijos = array_map(fn (Grupo $g): array => $g->participantes, self::repartir($fase->refresh(), $fijos, $modo, $cabezas ?? [], $repartidos)->grupos);
        }

        $resultado = app(EditarGrupos::class)->handle($fase->refresh(), $fijos, $usuario);

        if ($resultado['cambiados'] === []) {
            return ['Los grupos no cambian', $resultado['sinGrupo'] === [] ? null : 'Sin grupo: '.implode(', ', $resultado['sinGrupo']).'.'];
        }

        $cuerpo = 'Cambian: '.implode(', ', $resultado['cambiados']).' · '.$resultado['partidos'].' partidos nuevos.';
        if ($resultado['sinGrupo'] !== []) {
            $cuerpo .= ' Sin grupo: '.implode(', ', $resultado['sinGrupo']).'.';
        }

        return ['Grupos guardados', $cuerpo];
    }

    /** @param  list<int>  $cabezas */
    private static function guardarCabezas(Fase $fase, array $cabezas, User $usuario): void
    {
        $categoria = $fase->categoria;

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

    /** @return list<int> */
    private static function ids(mixed $valor): array
    {
        return array_values(array_unique(array_map(fn (mixed $id): int => (int) $id, is_array($valor) ? $valor : [])));
    }
}
