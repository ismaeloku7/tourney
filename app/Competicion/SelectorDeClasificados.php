<?php

namespace App\Competicion;

use App\Competicion\Datos\Clasificacion;
use App\Competicion\Datos\Clasificado;
use App\Competicion\Datos\LineaDeClasificacion;
use App\Competicion\Datos\Partido;
use App\Competicion\Datos\Puntuacion;
use App\Competicion\Datos\ReglaDeClasificados;
use App\Enums\CriterioDeDesempate;

/**
 * Quién pasa de la fase de grupos: los N primeros de cada grupo y, si la
 * regla lo pide, los M mejores de una posición comparados entre sí con
 * los mismos criterios; con grupos de distinto tamaño, a los de los
 * grupos grandes se les descartan los partidos contra el último de su
 * grupo. El orden global: todos los primeros, luego los segundos…, y los
 * «mejores» al final.
 */
final readonly class SelectorDeClasificados
{
    public function __construct(private CalculadorDeClasificacion $calculador = new CalculadorDeClasificacion) {}

    /**
     * @param  list<Clasificacion>  $clasificaciones
     * @param  list<CriterioDeDesempate>  $criterios
     * @param  list<Partido>  $partidos  Todos los de la fase (para ajustar a los mejores).
     * @param  array<int, string>  $nombresDeGrupo  Id de grupo → letra, para el origen.
     * @return list<Clasificado>
     */
    public function seleccionar(array $clasificaciones, ReglaDeClasificados $regla, array $criterios, Puntuacion $puntuacion, array $partidos, array $nombresDeGrupo = []): array
    {
        $porPosicion = [];
        $candidatosAMejor = [];
        $tamanoMinimo = min(array_map(fn (Clasificacion $c): int => count($c->lineas), $clasificaciones) ?: [0]);

        foreach ($clasificaciones as $clasificacion) {
            $nombre = $nombresDeGrupo[$clasificacion->grupo] ?? (string) $clasificacion->grupo;

            foreach ($clasificacion->lineas as $linea) {
                if ($linea->posicion <= $regla->porGrupo) {
                    $porPosicion[$linea->posicion][] = [$linea, $clasificacion->grupo, $linea->posicion.'º '.$nombre];
                } elseif ($regla->mejoresDePosicion !== null && $linea->posicion === $regla->mejoresDePosicion) {
                    $candidatosAMejor[] = [$this->ajustada($linea, $clasificacion, $partidos, $puntuacion, $criterios, $tamanoMinimo), $clasificacion->grupo, 'mejor '.$linea->posicion.'º ('.$nombre.')'];
                }
            }
        }

        ksort($porPosicion);
        $clasificados = [];
        $orden = 1;

        foreach ($porPosicion as $posicion => $candidatos) {
            foreach ($this->ordenar($candidatos, $criterios) as [$linea, $grupo, $origen]) {
                $clasificados[] = new Clasificado($linea->participante, $grupo, $posicion, $origen, $orden++);
            }
        }

        if ($regla->mejoresDePosicion !== null && $regla->cuantosMejores > 0) {
            $mejores = array_slice($this->ordenar($candidatosAMejor, $criterios), 0, $regla->cuantosMejores);

            foreach ($mejores as [$linea, $grupo, $origen]) {
                $clasificados[] = new Clasificado($linea->participante, $grupo, $regla->mejoresDePosicion, $origen, $orden++);
            }
        }

        return $clasificados;
    }

    /**
     * La línea de un candidato de un grupo grande sin sus partidos contra
     * los últimos de su grupo, para compararlo con justicia con los de los
     * grupos pequeños.
     *
     * @param  list<Partido>  $partidos
     * @param  list<CriterioDeDesempate>  $criterios
     */
    private function ajustada(LineaDeClasificacion $linea, Clasificacion $clasificacion, array $partidos, Puntuacion $puntuacion, array $criterios, int $tamanoMinimo): LineaDeClasificacion
    {
        $sobran = count($clasificacion->lineas) - $tamanoMinimo;

        if ($sobran <= 0) {
            return $linea;
        }

        $ultimos = array_map(fn (LineaDeClasificacion $l): int => $l->participante, array_slice($clasificacion->lineas, -$sobran));
        $miembros = array_values(array_diff($clasificacion->participantesEnOrden(), $ultimos));
        $sinUltimos = array_values(array_filter($partidos, fn (Partido $p): bool => ! in_array($p->local, $ultimos, true) && ! in_array($p->visitante, $ultimos, true)));

        $recalculada = $this->calculador->calcular($miembros, $sinUltimos, $puntuacion, $criterios, [], $clasificacion->grupo);

        return ($recalculada->linea($linea->participante) ?? $linea)->con(posicion: $linea->posicion, sorteo: $linea->sorteo);
    }

    /**
     * Ordena candidatos de distintos grupos por los criterios de
     * estadística (el enfrentamiento directo no aplica entre grupos).
     *
     * @param  list<array{0: LineaDeClasificacion, 1: int|null, 2: string}>  $candidatos
     * @param  list<CriterioDeDesempate>  $criterios
     * @return list<array{0: LineaDeClasificacion, 1: int|null, 2: string}>
     */
    private function ordenar(array $candidatos, array $criterios): array
    {
        usort($candidatos, function (array $a, array $b) use ($criterios): int {
            foreach ($criterios as $criterio) {
                $comparacion = $this->comparar($criterio, $b[0], $a[0]);
                if ($comparacion !== 0) {
                    return $comparacion;
                }
            }

            return $a[0]->participante <=> $b[0]->participante;
        });

        return $candidatos;
    }

    private function comparar(CriterioDeDesempate $criterio, LineaDeClasificacion $a, LineaDeClasificacion $b): int
    {
        return match ($criterio) {
            CriterioDeDesempate::Puntos => $a->puntos <=> $b->puntos,
            CriterioDeDesempate::DiferenciaDeSets => $a->diferenciaDeSets() <=> $b->diferenciaDeSets(),
            CriterioDeDesempate::SetsGanados => $a->setsFavor <=> $b->setsFavor,
            CriterioDeDesempate::DiferenciaDeJuegos => $a->diferenciaDeJuegos() <=> $b->diferenciaDeJuegos(),
            CriterioDeDesempate::JuegosGanados => $a->juegosFavor <=> $b->juegosFavor,
            CriterioDeDesempate::Sorteo => ($a->sorteo ?? 0) <=> ($b->sorteo ?? 0),
            CriterioDeDesempate::EnfrentamientoDirecto => 0,
        };
    }
}
