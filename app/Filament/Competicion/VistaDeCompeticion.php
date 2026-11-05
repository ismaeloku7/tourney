<?php

namespace App\Filament\Competicion;

use App\Actions\Competicion\ProyectarCruces;
use App\Competicion\Datos\CruceProyectado;
use App\Models\Categoria;
use App\Models\Clasificacion;
use App\Models\Fase;
use App\Models\Grupo;
use App\Models\Partido;
use Illuminate\Database\Eloquent\Collection;

/**
 * La competición de una categoría tal como la enseña el panel: cada fase
 * de grupos con sus grupos (clasificación, cruce previsto y partidos) y
 * cada cuadro por rondas, con nombres completos y también las fases que
 * la web no enseña. Solo lectura: las acciones van aparte.
 */
final class VistaDeCompeticion
{
    public function __construct(private readonly ProyectarCruces $cruces = new ProyectarCruces) {}

    /**
     * @return list<array{fase: Fase, jugados: int, total: int, grupos: list<array{grupo: Grupo, lineas: Collection<int, Clasificacion>, cruces: array<int, CruceProyectado>, partidos: Collection<int, Partido>}>, rondas: list<array{nombre: string, partidos: Collection<int, Partido>}>}>
     */
    public function deCategoria(Categoria $categoria): array
    {
        $fases = $categoria->fases()->orderBy('orden')
            ->with(['grupos.clasificaciones.participante', 'grupos.partidos.local', 'grupos.partidos.visitante', 'grupos.partidos.sets', 'grupos.partidos.franja', 'grupos.partidos.pista', 'partidos.local', 'partidos.visitante', 'partidos.sets', 'partidos.franja', 'partidos.pista'])
            ->get();

        return array_values($fases->map(function (Fase $fase): array {
            $partidos = $fase->partidos;
            $conRivales = $partidos->filter(fn (Partido $p): bool => $p->local_id !== null && $p->visitante_id !== null);
            $jugados = $conRivales->filter(fn (Partido $p): bool => $p->cerrado())->count();

            return [
                'fase' => $fase,
                'jugados' => $jugados,
                'total' => $conRivales->count(),
                'grupos' => $fase->tipo->esDeGrupos() ? $this->grupos($fase) : [],
                'rondas' => $fase->tipo->esDeCuadro() ? $this->rondas($fase) : [],
            ];
        })->all());
    }

    /**
     * @return list<array{grupo: Grupo, lineas: Collection<int, Clasificacion>, cruces: array<int, CruceProyectado>, partidos: Collection<int, Partido>}>
     */
    private function grupos(Fase $fase): array
    {
        $cruces = $this->cruces->handle($fase);

        return array_values($fase->grupos->map(fn (Grupo $grupo): array => [
            'grupo' => $grupo,
            'lineas' => $grupo->clasificaciones,
            'cruces' => $cruces,
            'partidos' => $grupo->partidos->sortBy(fn (Partido $p): string => ($p->franja !== null ? $p->franja->inicio->format('Y-m-d H:i') : '9999').'-'.str_pad((string) $p->jornada, 3, '0', STR_PAD_LEFT).'-'.$p->id)->values(),
        ])->all());
    }

    /**
     * @return list<array{nombre: string, partidos: Collection<int, Partido>}>
     */
    private function rondas(Fase $fase): array
    {
        $partidos = $fase->partidos->sortBy(fn (Partido $p): int => ($p->ronda ?? 0) * 1000 + ($p->posicion ?? 0))->values();
        $principales = $partidos->where('es_tercer_puesto', false);
        $rondas = (int) $principales->max('ronda');
        $lista = [];

        foreach ($principales->groupBy('ronda') as $ronda => $delaRonda) {
            $lista[] = ['nombre' => Marcador::ronda((int) $ronda, $rondas), 'partidos' => $delaRonda->values()];
        }

        $tercero = $partidos->where('es_tercer_puesto', true);
        if ($tercero->isNotEmpty()) {
            $lista[] = ['nombre' => 'Tercer puesto', 'partidos' => $tercero->values()];
        }

        return $lista;
    }
}
