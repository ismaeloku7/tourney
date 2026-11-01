<?php

namespace App\Competicion;

use App\Competicion\Datos\Clasificacion;
use App\Competicion\Datos\LineaDeClasificacion;
use App\Competicion\Datos\Partido;
use App\Competicion\Datos\Puntuacion;
use App\Enums\CriterioDeDesempate;
use App\Enums\EstadoPartido;
use App\Enums\ResultadoEspecial;

/**
 * La tabla de un grupo a partir de sus partidos cerrados, ordenada por los
 * criterios de desempate en orden: enfrentamiento directo solo entre dos;
 * con tres o más, mini-liga entre las empatadas y al siguiente criterio
 * solo las que siguen empatadas. El sorteo, una vez hecho, se devuelve en
 * la línea para que la aplicación lo persista y no cambie entre recálculos.
 */
final readonly class CalculadorDeClasificacion
{
    /**
     * @param  list<int>  $participantes
     * @param  list<Partido>  $partidos
     * @param  list<CriterioDeDesempate>  $criterios
     * @param  array<int, int>  $sorteosPrevios  Participante → número ya sorteado.
     */
    public function calcular(array $participantes, array $partidos, Puntuacion $puntuacion, array $criterios, array $sorteosPrevios = [], ?int $grupo = null): Clasificacion
    {
        $participantes = array_values(array_unique($participantes));
        $propios = array_values(array_filter($partidos, fn (Partido $p): bool => $p->local !== null && $p->visitante !== null && in_array($p->local, $participantes, true) && in_array($p->visitante, $participantes, true)));

        $lineas = $this->lineas($participantes, $propios, $puntuacion);
        $sorteos = $sorteosPrevios;
        $separadoPor = [];

        $orden = $this->ordenar($participantes, $lineas, $propios, $puntuacion, $criterios, 0, $sorteos, $separadoPor);

        $resultado = [];
        foreach ($orden as $posicion => $id) {
            $resultado[] = $lineas[$id]->con(
                posicion: $posicion + 1,
                criterioAplicado: $separadoPor[$id] ?? null,
                sorteo: $sorteos[$id] ?? null,
            );
        }

        $completa = $propios !== [] && array_reduce($propios, fn (bool $ok, Partido $p): bool => $ok && $p->cerrado(), true);

        return new Clasificacion($grupo, $resultado, $completa);
    }

    /**
     * Las estadísticas en bruto de cada participante.
     *
     * @param  list<int>  $participantes
     * @param  list<Partido>  $partidos
     * @return array<int, LineaDeClasificacion>
     */
    private function lineas(array $participantes, array $partidos, Puntuacion $puntuacion): array
    {
        /** @var array<int, AcumuladoDeLinea> $acumulado */
        $acumulado = [];
        foreach ($participantes as $id) {
            $acumulado[$id] = new AcumuladoDeLinea($id);
        }

        foreach ($partidos as $partido) {
            if (! $partido->cerrado()) {
                continue;
            }

            $local = $partido->local;
            $visitante = $partido->visitante;
            assert($local !== null && $visitante !== null);
            $especial = $partido->resultado?->especial;

            $acumulado[$local]->pj++;
            $acumulado[$visitante]->pj++;

            if ($especial === ResultadoEspecial::NoPresentado || $partido->ganador === null) {
                $acumulado[$local]->puntos += $puntuacion->noJugado;
                $acumulado[$visitante]->puntos += $puntuacion->noJugado;

                continue;
            }

            $ganador = $acumulado[$partido->ganador];
            $perdedor = $acumulado[$partido->ganador === $local ? $visitante : $local];
            $ganador->pg++;
            $perdedor->pp++;

            if ($especial === ResultadoEspecial::Walkover || $partido->estado === EstadoPartido::Walkover) {
                $ganador->puntos += $puntuacion->walkoverFavor;
                $perdedor->puntos += $puntuacion->walkoverContra;
                $ganador->setsFavor += $puntuacion->walkoverSets;
                $perdedor->setsContra += $puntuacion->walkoverSets;
                $ganador->juegosFavor += $puntuacion->walkoverSets * $puntuacion->walkoverJuegosPorSet;
                $perdedor->juegosContra += $puntuacion->walkoverSets * $puntuacion->walkoverJuegosPorSet;

                continue;
            }

            $ganador->puntos += $puntuacion->victoria;
            $perdedor->puntos += $puntuacion->derrota;

            $resultado = $partido->resultado;
            $setsLocal = $resultado?->setsGanadosPorLocal() ?? 0;
            $setsVisitante = $resultado?->setsGanadosPorVisitante() ?? 0;
            $juegosLocal = $resultado?->juegosDeLocal() ?? 0;
            $juegosVisitante = $resultado?->juegosDeVisitante() ?? 0;

            $deLocal = $acumulado[$local];
            $deVisitante = $acumulado[$visitante];
            $deLocal->setsFavor += $setsLocal;
            $deLocal->setsContra += $setsVisitante;
            $deVisitante->setsFavor += $setsVisitante;
            $deVisitante->setsContra += $setsLocal;
            $deLocal->juegosFavor += $juegosLocal;
            $deLocal->juegosContra += $juegosVisitante;
            $deVisitante->juegosFavor += $juegosVisitante;
            $deVisitante->juegosContra += $juegosLocal;

            if ($puntuacion->porSetGanado !== null) {
                $deLocal->puntos += $puntuacion->porSetGanado * $setsLocal;
                $deVisitante->puntos += $puntuacion->porSetGanado * $setsVisitante;
            }
        }

        $lineas = [];
        foreach ($acumulado as $id => $cuentas) {
            $lineas[$id] = $cuentas->linea();
        }

        return $lineas;
    }

    /**
     * Ordena `$ids` aplicando los criterios desde `$desde`. Devuelve los
     * ids en orden y anota en `$separadoPor` qué criterio separó cada uno
     * del siguiente.
     *
     * @param  list<int>  $ids
     * @param  array<int, LineaDeClasificacion>  $lineas
     * @param  list<Partido>  $partidos
     * @param  list<CriterioDeDesempate>  $criterios
     * @param  array<int, int>  $sorteos
     * @param  array<int, CriterioDeDesempate>  $separadoPor
     * @return list<int>
     */
    private function ordenar(array $ids, array $lineas, array $partidos, Puntuacion $puntuacion, array $criterios, int $desde, array &$sorteos, array &$separadoPor): array
    {
        if (count($ids) <= 1) {
            return $ids;
        }

        if (! isset($criterios[$desde])) {
            // Sin más criterios: orden estable por id.
            sort($ids);

            return $ids;
        }

        $criterio = $criterios[$desde];

        if ($criterio === CriterioDeDesempate::EnfrentamientoDirecto) {
            $subgrupos = count($ids) === 2
                ? $this->porEnfrentamientoDirecto($ids, $partidos)
                : $this->porMiniLiga($ids, $partidos, $puntuacion, $criterios);
        } else {
            $subgrupos = $this->porClave($ids, function (int $id) use ($criterio, $lineas, &$sorteos): int {
                return $this->clave($criterio, $lineas[$id], $sorteos);
            });
        }

        $orden = [];
        $ultimo = count($subgrupos) - 1;

        foreach ($subgrupos as $i => $subgrupo) {
            $ordenado = $this->ordenar($subgrupo, $lineas, $partidos, $puntuacion, $criterios, $desde + 1, $sorteos, $separadoPor);

            if ($i < $ultimo) {
                $separadoPor[$ordenado[count($ordenado) - 1]] = $criterio;
            }

            $orden = [...$orden, ...$ordenado];
        }

        return $orden;
    }

    /**
     * @param  array<int, int>  $sorteos
     */
    private function clave(CriterioDeDesempate $criterio, LineaDeClasificacion $linea, array &$sorteos): int
    {
        return match ($criterio) {
            CriterioDeDesempate::Puntos => $linea->puntos,
            CriterioDeDesempate::DiferenciaDeSets => $linea->diferenciaDeSets(),
            CriterioDeDesempate::SetsGanados => $linea->setsFavor,
            CriterioDeDesempate::DiferenciaDeJuegos => $linea->diferenciaDeJuegos(),
            CriterioDeDesempate::JuegosGanados => $linea->juegosFavor,
            CriterioDeDesempate::Sorteo => $sorteos[$linea->participante] ??= random_int(1, 1_000_000_000),
            CriterioDeDesempate::EnfrentamientoDirecto => 0,
        };
    }

    /**
     * Parte los ids en subgrupos de mayor a menor clave; los de la misma
     * clave quedan juntos.
     *
     * @param  list<int>  $ids
     * @param  callable(int): int  $clave
     * @return list<list<int>>
     */
    private function porClave(array $ids, callable $clave): array
    {
        $porValor = [];
        foreach ($ids as $id) {
            $porValor[$clave($id)][] = $id;
        }
        krsort($porValor, SORT_NUMERIC);

        return array_values($porValor);
    }

    /**
     * Entre dos: quien ganó el partido entre ambos va delante. Sin partido
     * cerrado entre ellos, siguen empatados.
     *
     * @param  list<int>  $ids
     * @param  list<Partido>  $partidos
     * @return list<list<int>>
     */
    private function porEnfrentamientoDirecto(array $ids, array $partidos): array
    {
        [$a, $b] = $ids;
        $victoriasA = 0;
        $victoriasB = 0;

        foreach ($partidos as $partido) {
            if ($partido->cerrado() && $partido->juega($a) && $partido->juega($b)) {
                if ($partido->ganador === $a) {
                    $victoriasA++;
                } elseif ($partido->ganador === $b) {
                    $victoriasB++;
                }
            }
        }

        if ($victoriasA === $victoriasB) {
            return [$ids];
        }

        return $victoriasA > $victoriasB ? [[$a], [$b]] : [[$b], [$a]];
    }

    /**
     * Con tres o más empatados: la clasificación solo con los partidos entre
     * ellos, con los criterios de estadística en el mismo orden. Los que
     * sigan empatados en la mini-liga se quedan en el mismo subgrupo.
     *
     * @param  list<int>  $ids
     * @param  list<Partido>  $partidos
     * @param  list<CriterioDeDesempate>  $criterios
     * @return list<list<int>>
     */
    private function porMiniLiga(array $ids, array $partidos, Puntuacion $puntuacion, array $criterios): array
    {
        $entreEllos = array_values(array_filter($partidos, fn (Partido $p): bool => in_array($p->local, $ids, true) && in_array($p->visitante, $ids, true)));

        if ($entreEllos === []) {
            return [$ids];
        }

        $lineas = $this->lineas($ids, $entreEllos, $puntuacion);
        $deEstadistica = array_values(array_filter($criterios, fn (CriterioDeDesempate $c): bool => $c !== CriterioDeDesempate::EnfrentamientoDirecto && $c !== CriterioDeDesempate::Sorteo));

        if (! in_array(CriterioDeDesempate::Puntos, $deEstadistica, true)) {
            array_unshift($deEstadistica, CriterioDeDesempate::Puntos);
        }

        $subgrupos = [$ids];
        $nada = [];

        foreach ($deEstadistica as $criterio) {
            $siguientes = [];
            foreach ($subgrupos as $subgrupo) {
                if (count($subgrupo) <= 1) {
                    $siguientes[] = $subgrupo;

                    continue;
                }
                foreach ($this->porClave($subgrupo, fn (int $id): int => $this->clave($criterio, $lineas[$id], $nada)) as $parte) {
                    $siguientes[] = $parte;
                }
            }
            $subgrupos = $siguientes;
        }

        return $subgrupos;
    }
}
