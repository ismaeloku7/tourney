<?php

namespace App\Publico;

use App\Enums\EstadoInscripcion;
use App\Enums\EstadoPartido;
use App\Filament\Competicion\Marcador;
use App\Models\Categoria;
use App\Models\Fase;
use App\Models\Grupo;
use App\Models\Inscripcion;
use App\Models\Partido;
use App\Models\Torneo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Lo que enseñan las páginas públicas de un torneo, ya escrito: inscritos
 * por categoría, grupos con su clasificación, cuadros por rondas y
 * horarios por día. Todo con los nombres públicos.
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
                'confirmadas' => $confirmadas->map(fn (Inscripcion $i): string => (string) NombrePublico::participante($i->participante, $this->torneo))->values()->all(),
                'espera' => $inscripciones->where('estado', EstadoInscripcion::ListaDeEspera)->count(),
                'plazas' => $categoria->cupo === null ? null : max(0, $categoria->cupo - $confirmadas->count()),
            ];
        }

        return $salida;
    }

    /**
     * @return list<array{categoria: Categoria, fase: Fase, grupos: list<array{grupo: Grupo, lineas: list<array{posicion: int, nombre: string, puntos: int, pj: int, pg: int, pp: int, sets: string, juegos: string, clasificado: bool}>, partidos: list<array{partido: string, resultado: string, hora: string|null}>}>}>
     */
    public function grupos(): array
    {
        $salida = [];

        foreach ($this->fases() as $fase) {
            if (! $fase->tipo->esDeGrupos() || $fase->grupos->isEmpty()) {
                continue;
            }

            $grupos = [];
            foreach ($fase->grupos as $grupo) {
                $grupos[] = [
                    'grupo' => $grupo,
                    'lineas' => $grupo->clasificaciones->map(fn ($linea): array => [
                        'posicion' => $linea->posicion,
                        'nombre' => (string) NombrePublico::participante($linea->participante, $this->torneo),
                        'puntos' => $linea->puntos, 'pj' => $linea->pj, 'pg' => $linea->pg, 'pp' => $linea->pp,
                        'sets' => "{$linea->sets_favor}-{$linea->sets_contra}",
                        'juegos' => "{$linea->juegos_favor}-{$linea->juegos_contra}",
                        'clasificado' => $linea->clasificado_como !== null,
                    ])->values()->all(),
                    'partidos' => $grupo->partidos->map(fn (Partido $p): array => $this->partido($p))->values()->all(),
                ];
            }

            $salida[] = ['categoria' => $fase->categoria, 'fase' => $fase, 'grupos' => $grupos];
        }

        return $salida;
    }

    /**
     * @return list<array{categoria: Categoria, fase: Fase, rondas: list<array{nombre: string, partidos: list<array{partido: string, resultado: string, hora: string|null}>}>}>
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
                $lista[] = ['nombre' => Marcador::ronda((int) $ronda, $rondas), 'partidos' => $delaRonda->map(fn (Partido $p): array => $this->partido($p))->values()->all()];
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
     * Los partidos con hora, por día. Sin horarios publicados, nada.
     *
     * @return array<string, list<array{hora: string, pista: string, categoria: string, partido: string, resultado: string, estado: EstadoPartido}>>
     */
    public function horarios(): array
    {
        if ($this->torneo->horarios_publicados_en === null) {
            return [];
        }

        $salida = [];

        foreach ($this->partidosConHora()->get() as $p) {
            $dia = $p->franja->inicio->translatedFormat('l j \d\e F');
            $salida[$dia][] = [
                'hora' => $p->franja->inicio->format('H:i'),
                'pista' => $p->pista?->nombre ?? '',
                'categoria' => $p->fase->categoria->nombre,
                'partido' => $this->partido($p)['partido'],
                'resultado' => $p->cerrado() ? Marcador::resultado($p) : '',
                'estado' => $p->estado,
            ];
        }

        return $salida;
    }

    /**
     * Los partidos con hora, en orden, para el tablero y las pistas.
     *
     * @return Builder<Partido>
     */
    public function partidosConHora(): Builder
    {
        return Partido::query()
            ->whereHas('fase.categoria', fn (Builder $q) => $q->where('torneo_id', $this->torneo->id))
            ->whereNotNull('franja_id')->whereNotNull('local_id')->whereNotNull('visitante_id')
            ->with(['fase.categoria', 'local.jugadores', 'visitante.jugadores', 'ganador', 'pista', 'franja', 'sets'])
            ->join('franjas', 'franjas.id', '=', 'partidos.franja_id')
            ->orderBy('franjas.inicio')->orderBy('partidos.pista_id')
            ->select('partidos.*');
    }

    /** @return array{partido: string, resultado: string, hora: string|null, ganaLocal: bool|null} */
    public function partido(Partido $p): array
    {
        $local = NombrePublico::participante($p->local, $this->torneo) ?? Marcador::lado($p, true);
        $visitante = NombrePublico::participante($p->visitante, $this->torneo) ?? Marcador::lado($p, false);

        return [
            'partido' => "{$local} – {$visitante}",
            'resultado' => $p->cerrado() ? Marcador::resultado($p) : '',
            'hora' => $p->franja !== null && $this->torneo->horarios_publicados_en !== null ? $p->franja->inicio->translatedFormat('D j/n H:i').($p->pista !== null ? ' · '.$p->pista->nombre : '') : null,
            'ganaLocal' => $p->ganador_id === null ? null : $p->ganador_id === $p->local_id,
        ];
    }

    /** @return Collection<int, Fase> */
    private function fases(): Collection
    {
        return Fase::query()->whereIn('categoria_id', $this->torneo->categorias->pluck('id'))
            ->with(['categoria', 'grupos.clasificaciones.participante.jugadores', 'grupos.partidos.local.jugadores', 'grupos.partidos.visitante.jugadores', 'grupos.partidos.sets', 'grupos.partidos.franja', 'grupos.partidos.pista', 'partidos.local.jugadores', 'partidos.visitante.jugadores', 'partidos.ganador', 'partidos.sets', 'partidos.franja', 'partidos.pista'])
            ->get()
            ->sortBy(fn (Fase $f): int => $f->categoria->orden * 100 + $f->orden)
            ->values();
    }
}
