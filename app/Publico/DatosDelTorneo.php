<?php

namespace App\Publico;

use App\Actions\Competicion\AnotarMarcador;
use App\Actions\Competicion\ProyectarCruces;
use App\Competicion\Datos\Origen;
use App\Competicion\Datos\Partido as PartidoDto;
use App\Competicion\EsquemaDeCuadro;
use App\Enums\EstadoInscripcion;
use App\Enums\EstadoPartido;
use App\Enums\TipoDeOrigen;
use App\Filament\Competicion\Marcador;
use App\Models\Categoria;
use App\Models\Clasificacion;
use App\Models\Fase;
use App\Models\Grupo;
use App\Models\Inscripcion;
use App\Models\Participante;
use App\Models\Partido;
use App\Models\Torneo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Lo que enseñan las páginas públicas de un torneo, ya escrito: inscritos
 * por categoría, grupos con su clasificación, cuadros por rondas y
 * horarios por día. Todo con los nombres públicos, y solo lo que la
 * organización deja ver: las fases ocultas no salen, y sin horarios
 * visibles no hay horas, ni tablero, ni partidos de pista.
 */
final class DatosDelTorneo
{
    public function __construct(public readonly Torneo $torneo) {}

    /**
     * @return list<array{categoria: Categoria, confirmadas: list<string>, espera: int, plazas: int|null}>
     */
    public function inscritos(): array
    {
        $salida = [];

        foreach ($this->torneo->categorias as $categoria) {
            $inscripciones = Inscripcion::query()->where('categoria_id', $categoria->id)
                ->whereIn('estado', [EstadoInscripcion::Confirmada->value, EstadoInscripcion::ListaDeEspera->value])
                ->with('participante.jugadores')->orderBy('id')->get();

            $confirmadas = $inscripciones->where('estado', EstadoInscripcion::Confirmada);

            $salida[] = [
                'categoria' => $categoria,
                'confirmadas' => array_values($confirmadas->map(fn (Inscripcion $i): string => (string) NombrePublico::participante($i->participante, $this->torneo))->all()),
                'espera' => $inscripciones->where('estado', EstadoInscripcion::ListaDeEspera)->count(),
                'plazas' => $categoria->cupo === null ? null : max(0, $categoria->cupo - $confirmadas->count()),
            ];
        }

        return $salida;
    }

    /**
     * Con la fase de grupos abierta y un cuadro detrás que no se sortea,
     * cada línea lleva el cruce previsto («2º B», «Bye») y `cruces` lo dice.
     *
     * @return list<array{categoria: Categoria, fase: Fase, cruces: bool, grupos: list<array{grupo: Grupo, lineas: list<array{participante: int, posicion: int, nombre: string, puntos: int, pj: int, pg: int, pp: int, sets: string, juegos: string, clasificado: bool, cruce: string|null}>, partidos: list<array{partido: string, resultado: string, hora: string|null, enVivo: string, ganaLocal: bool|null}>}>}>
     */
    public function grupos(): array
    {
        $salida = [];

        foreach ($this->fases() as $fase) {
            if (! $fase->tipo->esDeGrupos() || $fase->grupos->isEmpty()) {
                continue;
            }

            $cruces = app(ProyectarCruces::class)->handle($fase);
            $grupos = [];
            foreach ($fase->grupos as $grupo) {
                $grupos[] = [
                    'grupo' => $grupo,
                    'lineas' => array_values($grupo->clasificaciones->map(fn (Clasificacion $linea): array => [
                        'participante' => $linea->participante_id,
                        'posicion' => $linea->posicion,
                        'nombre' => (string) NombrePublico::participante($linea->participante, $this->torneo),
                        'puntos' => $linea->puntos, 'pj' => $linea->pj, 'pg' => $linea->pg, 'pp' => $linea->pp,
                        'sets' => "{$linea->sets_favor}-{$linea->sets_contra}",
                        'juegos' => "{$linea->juegos_favor}-{$linea->juegos_contra}",
                        'clasificado' => $linea->clasificado_como !== null,
                        'cruce' => isset($cruces[$linea->participante_id]) ? $cruces[$linea->participante_id]->etiqueta() : null,
                    ])->all()),
                    'partidos' => array_values($grupo->partidos->map(fn (Partido $p): array => $this->partido($p))->all()),
                ];
            }

            $salida[] = ['categoria' => $fase->categoria, 'fase' => $fase, 'cruces' => $cruces !== [], 'grupos' => $grupos];
        }

        return $salida;
    }

    /**
     * @return list<array{categoria: Categoria, fase: Fase, rondas: list<array{nombre: string, partidos: list<array{partido: string, resultado: string, hora: string|null, enVivo: string, ganaLocal: bool|null}>}>}>
     */
    public function cuadros(): array
    {
        $salida = [];

        foreach ($this->fases() as $fase) {
            if (! $fase->tipo->esDeCuadro() || $fase->partidos->isEmpty()) {
                continue;
            }

            $partidos = $fase->partidos->sortBy(fn (Partido $p): int => ($p->ronda ?? 0) * 1000 + ($p->posicion ?? 0))->values();
            $rondas = (int) $partidos->where('es_tercer_puesto', false)->max('ronda');
            $lista = [];

            foreach ($partidos->where('es_tercer_puesto', false)->groupBy('ronda') as $ronda => $delaRonda) {
                $lista[] = ['nombre' => Marcador::ronda((int) $ronda, $rondas), 'partidos' => array_values($delaRonda->map(fn (Partido $p): array => $this->partido($p))->all())];
            }

            $tercero = $partidos->firstWhere('es_tercer_puesto', true);
            if ($tercero !== null) {
                $lista[] = ['nombre' => 'Tercer puesto', 'partidos' => [$this->partido($tercero)]];
            }

            $salida[] = ['categoria' => $fase->categoria, 'fase' => $fase, 'rondas' => $lista];
        }

        return $salida;
    }

    /**
     * Con la fase de grupos en juego y el cuadro que va detrás aún sin
     * hacer (y visible), el cuadro entero tal y como saldría si los grupos
     * acabasen ahora: cada pareja con su posición («Ana / Eva (1º A)») y en
     * las rondas siguientes de qué cruce sale cada una («Ganador C1»). Por
     * sorteo no hay nada que adelantar.
     *
     * @return list<array{categoria: Categoria, fase: Fase, previsto: true, rondas: list<array{nombre: string, partidos: list<array{partido: string, codigo: string, resultado: string, hora: string|null, enVivo: string, ganaLocal: bool|null}>}>}>
     */
    public function cuadrosPrevistos(): array
    {
        $salida = [];

        foreach ($this->fases() as $fase) {
            $cuadro = $fase->tipo->esDeGrupos() ? $fase->siguiente() : null;
            if ($cuadro === null || ! $cuadro->visible_en_web || $cuadro->partidos()->exists()) {
                continue;
            }

            $previsto = app(ProyectarCruces::class)->cuadro($fase);
            if ($previsto === null) {
                continue;
            }
            [$construido, $clasificados] = $previsto;

            $nombres = Participante::query()->with('jugadores')->whereIn('id', array_keys($clasificados))->get()
                ->mapWithKeys(fn (Participante $p): array => [$p->id => (string) NombrePublico::participante($p, $this->torneo)])->all();

            // Cada cruce con su código: O1…, C1…, S1, S2, F.
            $codigo = [];
            for ($ronda = 1; $ronda <= $construido->rondas; $ronda++) {
                $deRonda = $construido->partidosDeRonda($ronda);
                foreach ($deRonda as $partido) {
                    $codigo[(int) $partido->id] = EsquemaDeCuadro::prefijo(count($deRonda)).($ronda === $construido->rondas ? '' : $partido->posicion);
                }
            }

            $hueco = function (?int $participante, ?Origen $origen) use ($nombres, $clasificados, $codigo): string {
                if ($participante !== null) {
                    return ($nombres[$participante] ?? '?').' ('.($clasificados[$participante]->origen ?? '?').')';
                }
                if ($origen !== null) {
                    return ($origen->tipo === TipoDeOrigen::PerdedorDe ? 'Perdedor ' : 'Ganador ').($codigo[$origen->referencia] ?? '?');
                }

                return 'Bye';
            };
            $fila = fn (PartidoDto $p, string $codigoDelPartido): array => [
                'partido' => $hueco($p->local, $p->local === null ? $p->origenLocal : null).' – '.$hueco($p->visitante, $p->visitante === null ? $p->origenVisitante : null),
                'codigo' => $codigoDelPartido,
                'resultado' => '',
                'hora' => null,
                'enVivo' => '',
                'ganaLocal' => null,
            ];

            $rondas = [];
            for ($ronda = 1; $ronda <= $construido->rondas; $ronda++) {
                $rondas[] = [
                    'nombre' => Marcador::ronda($ronda, $construido->rondas),
                    'partidos' => array_map(fn (PartidoDto $p): array => $fila($p, $codigo[(int) $p->id]), $construido->partidosDeRonda($ronda)),
                ];
            }
            $tercero = $construido->tercerPuesto();
            if ($tercero !== null) {
                $rondas[] = ['nombre' => 'Tercer puesto', 'partidos' => [$fila($tercero, '3º')]];
            }

            $salida[] = ['categoria' => $fase->categoria, 'fase' => $cuadro, 'previsto' => true, 'rondas' => $rondas];
        }

        return $salida;
    }

    /**
     * Los partidos con hora, por día. Sin horarios publicados, nada.
     *
     * @return array<string, list<array{hora: string, pista: string, categoria: string, partido: string, resultado: string, estado: EstadoPartido}>>
     */
    public function horarios(): array
    {
        if (! $this->horariosVisibles()) {
            return [];
        }

        $salida = [];

        foreach ($this->partidosConHora()->get() as $p) {
            $dia = $p->franja->inicio->translatedFormat('l j \d\e F');
            $salida[$dia][] = [
                'hora' => $p->franja->inicio->format('H:i'),
                'pista' => $p->pista->nombre ?? '',
                'categoria' => $p->fase->categoria->nombre,
                'partido' => $this->partido($p)['partido'],
                'resultado' => $p->cerrado() ? Marcador::resultado($p) : '',
                'estado' => $p->estado,
            ];
        }

        return $salida;
    }

    /**
     * Los partidos con hora, en orden, para el tablero y las pistas. Solo
     * de las fases visibles y con los horarios publicados y enseñados.
     *
     * @return Builder<Partido>
     */
    public function partidosConHora(): Builder
    {
        return Partido::query()
            ->whereHas('fase', fn (Builder $q) => $q->where('visible_en_web', true)->whereHas('categoria', fn (Builder $c) => $c->where('torneo_id', $this->torneo->id)))
            ->when(! $this->horariosVisibles(), fn (Builder $q) => $q->whereRaw('1 = 0'))
            ->whereNotNull('franja_id')->whereNotNull('local_id')->whereNotNull('visitante_id')
            ->with(['fase.categoria', 'local.jugadores', 'visitante.jugadores', 'ganador', 'pista', 'franja', 'sets'])
            ->join('franjas', 'franjas.id', '=', 'partidos.franja_id')
            ->orderBy('franjas.inicio')->orderBy('partidos.pista_id')
            ->select('partidos.*');
    }

    /**
     * Un partido en juego lleva cómo va (`enVivo`), si la mesa o los
     * jugadores llevan el marcador.
     *
     * @return array{partido: string, resultado: string, hora: string|null, enVivo: string, ganaLocal: bool|null}
     */
    public function partido(Partido $p): array
    {
        $local = NombrePublico::participante($p->local, $this->torneo) ?? Marcador::lado($p, true);
        $visitante = NombrePublico::participante($p->visitante, $this->torneo) ?? Marcador::lado($p, false);

        return [
            'partido' => "{$local} – {$visitante}",
            'resultado' => $p->cerrado() ? Marcador::resultado($p) : '',
            'hora' => $p->franja !== null && $this->horariosVisibles() ? $p->franja->inicio->translatedFormat('D j/n H:i').($p->pista !== null ? ' · '.$p->pista->nombre : '') : null,
            'enVivo' => $p->estado === EstadoPartido::EnJuego ? AnotarMarcador::texto($p) : '',
            'ganaLocal' => $p->ganador_id === null ? null : $p->ganador_id === $p->local_id,
        ];
    }

    /** Las horas se ven si están publicadas y la organización no las oculta. */
    public function horariosVisibles(): bool
    {
        return $this->torneo->horarios_publicados_en !== null && $this->torneo->web_horarios;
    }

    /** @return Collection<int, Fase> */
    private function fases(): Collection
    {
        return Fase::query()->whereIn('categoria_id', $this->torneo->categorias->pluck('id'))->where('visible_en_web', true)
            ->with(['categoria', 'grupos.clasificaciones.participante.jugadores', 'grupos.partidos.local.jugadores', 'grupos.partidos.visitante.jugadores', 'grupos.partidos.sets', 'grupos.partidos.franja', 'grupos.partidos.pista', 'partidos.local.jugadores', 'partidos.visitante.jugadores', 'partidos.ganador', 'partidos.sets', 'partidos.franja', 'partidos.pista'])
            ->get()
            ->sortBy(fn (Fase $f): int => $f->categoria->orden * 100 + $f->orden)
            ->values();
    }
}
