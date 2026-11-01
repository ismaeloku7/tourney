<?php

namespace App\Competicion;

use App\Competicion\Datos\Asignacion;
use App\Competicion\Datos\Conflicto;
use App\Competicion\Datos\FranjaDisponible;
use App\Competicion\Datos\Intervalo;
use App\Competicion\Datos\PartidoAPlanificar;
use App\Competicion\Datos\Planificacion;
use App\Competicion\Datos\RestriccionesDePlanificacion;

/**
 * Asigna franja y pista a los partidos pendientes, de forma voraz: por
 * prioridad de categoría, ronda y jornada, cada partido toma la primera
 * franja libre en la que cabe sin solapar a ningún jugador, respetando
 * el descanso mínimo, las no disponibilidades y el orden de rondas. Lo
 * que no cabe vuelve como conflicto, con el motivo más cercano. Nunca
 * lanza. Los partidos ya fijados se respetan y ocupan.
 */
final readonly class Planificador
{
    /**
     * @param  list<PartidoAPlanificar>  $partidos
     * @param  list<FranjaDisponible>  $franjas
     */
    public function planificar(array $partidos, array $franjas, RestriccionesDePlanificacion $restricciones): Planificacion
    {
        $porId = [];
        foreach ($partidos as $partido) {
            $porId[$partido->id] = $partido;
        }

        /** @var array<int, FranjaDisponible> $asignadas partido → franja */
        $asignadas = $restricciones->partidosYaFijados;
        /** @var array<int, list<Intervalo>> $ocupacion jugador → intervalos */
        $ocupacion = [];

        foreach ($asignadas as $id => $franja) {
            foreach ($porId[$id]->jugadores ?? [] as $jugador) {
                $ocupacion[$jugador][] = $franja->intervalo();
            }
        }

        $franjasOcupadas = array_map(fn (FranjaDisponible $f): int => $f->id, $asignadas);
        $libres = array_values(array_filter($franjas, fn (FranjaDisponible $f): bool => ! in_array($f->id, $franjasOcupadas, true)));
        usort($libres, fn (FranjaDisponible $a, FranjaDisponible $b): int => [$a->inicio, $a->pista, $a->id] <=> [$b->inicio, $b->pista, $b->id]);

        $pendientes = array_values(array_filter($partidos, fn (PartidoAPlanificar $p): bool => ! isset($asignadas[$p->id])));
        usort($pendientes, fn (PartidoAPlanificar $a, PartidoAPlanificar $b): int => [$a->prioridadDeCategoria, $a->ronda ?? 0, $a->jornada ?? 0, $a->id] <=> [$b->prioridadDeCategoria, $b->ronda ?? 0, $b->jornada ?? 0, $b->id]);

        $asignaciones = [];
        $conflictos = [];

        foreach ($pendientes as $partido) {
            $conflicto = null;
            $elegida = null;

            // Dependencias sin hora: este tampoco puede tenerla.
            $noAntesDe = null;
            foreach ($partido->dependeDe as $previo) {
                if (! isset($asignadas[$previo])) {
                    $conflicto ??= new Conflicto($partido->id, Conflicto::ORDEN_DE_RONDAS, "El partido {$previo} tiene que jugarse antes y no tiene hora.");

                    continue 2;
                }
                if ($noAntesDe === null || $asignadas[$previo]->fin > $noAntesDe) {
                    $noAntesDe = $asignadas[$previo]->fin;
                }
            }

            foreach ($libres as $i => $franja) {
                if ($franja->duracionMinutos() < $partido->duracionMinutos) {
                    continue;
                }

                if ($noAntesDe !== null && $franja->inicio < $noAntesDe) {
                    $conflicto ??= new Conflicto($partido->id, Conflicto::ORDEN_DE_RONDAS, 'Los partidos de los que depende acaban después de esa franja.');

                    continue;
                }

                $motivo = $this->motivoParaRechazar($partido, $franja, $ocupacion, $restricciones);

                if ($motivo !== null) {
                    $conflicto ??= $motivo;

                    continue;
                }

                $elegida = $franja;
                unset($libres[$i]);

                break;
            }

            if ($elegida === null) {
                $conflictos[] = $conflicto ?? new Conflicto($partido->id, Conflicto::SIN_FRANJA, 'No queda ninguna franja libre en la que quepa.');

                continue;
            }

            $asignadas[$partido->id] = $elegida;
            $asignaciones[] = new Asignacion($partido->id, $elegida);

            foreach ($partido->jugadores as $jugador) {
                $ocupacion[$jugador][] = $elegida->intervalo();
            }
        }

        return new Planificacion($asignaciones, $conflictos);
    }

    /**
     * @param  array<int, list<Intervalo>>  $ocupacion
     */
    private function motivoParaRechazar(PartidoAPlanificar $partido, FranjaDisponible $franja, array $ocupacion, RestriccionesDePlanificacion $restricciones): ?Conflicto
    {
        $intervalo = $franja->intervalo();
        $descanso = $restricciones->descansoMinimoMinutos * 60;

        foreach ($partido->jugadores as $jugador) {
            foreach ($restricciones->noDisponibilidades[$jugador] ?? [] as $noPuede) {
                if ($intervalo->solapa($noPuede)) {
                    return new Conflicto($partido->id, Conflicto::JUGADOR_NO_DISPONIBLE, "El jugador {$jugador} no puede a las ".$franja->inicio->format('d/m H:i').'.');
                }
            }

            foreach ($ocupacion[$jugador] ?? [] as $ocupado) {
                if ($intervalo->solapa($ocupado)) {
                    return new Conflicto($partido->id, Conflicto::JUGADOR_SOLAPADO, "El jugador {$jugador} ya juega a las ".$ocupado->desde->format('d/m H:i').'.');
                }

                $hueco = max($intervalo->desde->getTimestamp() - $ocupado->hasta->getTimestamp(), $ocupado->desde->getTimestamp() - $intervalo->hasta->getTimestamp());

                if ($hueco < $descanso) {
                    return new Conflicto($partido->id, Conflicto::SIN_DESCANSO, "El jugador {$jugador} no tendría ".$restricciones->descansoMinimoMinutos.' minutos de descanso.');
                }
            }
        }

        return null;
    }
}
