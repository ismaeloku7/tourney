<?php

namespace App\Competicion;

use App\Competicion\Datos\PartidoEnDirecto;
use App\Competicion\Datos\SugerenciaDeRecolocacion;

/**
 * Las pistas durante el torneo, como colas: cada pista juega sus partidos
 * en orden y el siguiente entra en cuanto acaba el anterior, también si
 * acaba antes de lo previsto (los jugadores están en el club, pendientes).
 * Con lo que ya se ha jugado se recalcula la hora estimada de lo que
 * falta, respetando el descanso de cada jugador y que un partido no
 * empiece antes de que acaben, más el descanso, los que le dan sus
 * parejas. El primer partido del día de una pista no se adelanta. Quien
 * viene de jugar no calienta: su siguiente partido puede ir calentando
 * mientras acaba.
 *
 * Lo que se ha marcado como empezado es lo que está en su pista, desde la
 * hora a la que empezó: lo de detrás va a continuación.
 *
 * Si una pista se retrasa, propone pasar uno de sus partidos (nunca el
 * siguiente, y solo con tiempo para avisar) a otra pista que vaya bien.
 */
final readonly class PistasEnDirecto
{
    /**
     * La hora estimada de cada partido sin acabar: id → [inicio, fin].
     *
     * @param  list<PartidoEnDirecto>  $partidos
     * @param  int  $calentamientoMinutos  Lo que se reserva de pista antes de cada partido (incluido en sus minutos).
     * @return array<int, array{0: int, 1: int}>
     */
    public function recalcular(array $partidos, int $ahora, int $descansoMinutos, int $calentamientoMinutos = 0): array
    {
        $descanso = $descansoMinutos * 60;
        $calentamiento = $calentamientoMinutos * 60;
        $hoy = date('Y-m-d', $ahora);

        /** @var array<int, int> $libre pista → libre desde */
        $libre = [];
        /** @var array<int, bool> $conPartidoHoy pista → ya jugó (o juega) hoy */
        $conPartidoHoy = [];
        /** @var array<int, bool> $enJuego pista → su partido en juego ya se ha visto */
        $enJuego = [];
        /** @var array<int, int> $listo jugador → libre desde (con descanso) */
        $listo = [];
        /** @var array<int, int> $fin partido → fin */
        $fin = [];

        foreach ($partidos as $p) {
            if (! $p->cerrado) {
                continue;
            }
            $acaba = $p->fin();
            $libre[$p->pista] = max($libre[$p->pista] ?? PHP_INT_MIN, $acaba);
            if (date('Y-m-d', $p->inicio) === $hoy) {
                $conPartidoHoy[$p->pista] = true;
            }
            foreach ($p->jugadores as $jugador) {
                $listo[$jugador] = max($listo[$jugador] ?? PHP_INT_MIN, $acaba + $descanso);
            }
            $fin[$p->id] = $acaba;
        }

        $pendientes = array_values(array_filter($partidos, fn (PartidoEnDirecto $p): bool => ! $p->cerrado));
        // Lo que ya se está jugando va primero en su pista: los demás, detrás.
        usort($pendientes, fn (PartidoEnDirecto $a, PartidoEnDirecto $b): int => [$a->empezado ? 0 : 1, $a->orden ?? (float) $a->inicio, $a->pista, $a->id] <=> [$b->empezado ? 0 : 1, $b->orden ?? (float) $b->inicio, $b->pista, $b->id]);

        $salida = [];

        foreach ($pendientes as $p) {
            $duracion = $p->minutos * 60;
            $pista = $p->pista;

            // Ya en juego: es el primero de su pista, le tocaba antes de ahora y ni la pista ni sus jugadores estaban ocupados entonces.
            $pudoEmpezar = $p->orden === null && $p->inicio <= $ahora && ($libre[$pista] ?? PHP_INT_MIN) <= $p->inicio;
            foreach ($p->jugadores as $jugador) {
                $pudoEmpezar = $pudoEmpezar && ($listo[$jugador] ?? PHP_INT_MIN) - $descanso - $calentamiento <= $p->inicio;
            }
            foreach ($p->dependeDe as $previo) {
                $pudoEmpezar = $pudoEmpezar && ($fin[$previo] ?? PHP_INT_MIN) <= $p->inicio;
            }

            if (! isset($enJuego[$pista]) && ($p->empezado || $pudoEmpezar)) {
                // El que está en la pista: ya empezó; acaba cuando le toque, o en cualquier momento si se alarga.
                $inicio = $p->inicio;
                $acaba = max($inicio + $duracion, $ahora);
            } else {
                $adelantable = date('Y-m-d', $p->inicio) === $hoy && ($conPartidoHoy[$pista] ?? false);
                $inicio = $adelantable
                    ? max($libre[$pista] ?? $ahora, $ahora)
                    : max($p->inicio, $libre[$pista] ?? PHP_INT_MIN);

                // Quien viene de jugar no calienta: el partido puede empezar a calentar antes de que llegue.
                foreach ($p->jugadores as $jugador) {
                    $inicio = max($inicio, ($listo[$jugador] ?? PHP_INT_MIN) - $calentamiento);
                }
                foreach ($p->dependeDe as $previo) {
                    if (isset($fin[$previo])) {
                        $inicio = max($inicio, $fin[$previo] + $descanso - $calentamiento);
                    }
                }
                if ($p->noAntesDe !== null) {
                    $inicio = max($inicio, $p->noAntesDe);
                }
                $acaba = $inicio + $duracion;
            }

            $enJuego[$pista] = true;
            $libre[$pista] = $acaba;
            if (date('Y-m-d', $inicio) === $hoy) {
                $conPartidoHoy[$pista] = true;
            }
            foreach ($p->jugadores as $jugador) {
                $listo[$jugador] = $acaba + $descanso;
            }
            $fin[$p->id] = $acaba;
            $salida[$p->id] = [$inicio, $acaba];
        }

        return $salida;
    }

    /**
     * Cuántos minutos va tarde cada pista: su próximo partido por empezar
     * contra la hora a la que estaba previsto.
     *
     * @param  list<PartidoEnDirecto>  $partidos
     * @param  array<int, array{0: int, 1: int}>  $horas  Lo que devuelve recalcular().
     * @return array<int, int> pista → minutos (negativo si va adelantada)
     */
    public function retrasos(array $partidos, array $horas, int $ahora): array
    {
        $proximo = [];
        foreach ($partidos as $p) {
            if ($p->cerrado || $p->previsto === null || ! isset($horas[$p->id]) || $horas[$p->id][0] <= $ahora) {
                continue;
            }
            if (! isset($proximo[$p->pista]) || $horas[$p->id][0] < $horas[$proximo[$p->pista]->id][0]) {
                $proximo[$p->pista] = $p;
            }
        }

        return array_map(fn (PartidoEnDirecto $p): int => intdiv($horas[$p->id][0] - (int) $p->previsto, 60), $proximo);
    }

    /**
     * La mejor recolocación, si alguna pista va con $umbralMinutos o más
     * de retraso: uno de sus partidos que empiece dentro de $avisoMinutos
     * o más (nunca el siguiente) a otra pista que vaya bien, en el sitio
     * de su cola que más ahorre a todos. Null si no compensa.
     *
     * @param  list<PartidoEnDirecto>  $partidos
     */
    public function sugerencia(array $partidos, int $ahora, int $descansoMinutos, int $umbralMinutos = 20, int $avisoMinutos = 30, int $calentamientoMinutos = 0): ?SugerenciaDeRecolocacion
    {
        $horas = $this->recalcular($partidos, $ahora, $descansoMinutos, $calentamientoMinutos);
        $retrasos = $this->retrasos($partidos, $horas, $ahora);
        $atrasadas = array_filter($retrasos, fn (int $minutos): bool => $minutos >= $umbralMinutos);

        if ($atrasadas === []) {
            return null;
        }
        arsort($atrasadas);

        // Todo con su hora de ahora, para simular sobre lo mismo que se ve.
        $actuales = array_map(fn (PartidoEnDirecto $p): PartidoEnDirecto => isset($horas[$p->id]) ? $p->con($p->pista, $horas[$p->id][0]) : $p, $partidos);
        [$finBase, $retrasoBase] = $this->medir($actuales, $horas);
        $limite = $ahora + $avisoMinutos * 60;
        $pistas = array_values(array_unique(array_map(fn (PartidoEnDirecto $p): int => $p->pista, $partidos)));
        $mejor = null;
        $mejorPuntos = 14;

        foreach (array_keys($atrasadas) as $desde) {
            $cola = $this->cola($actuales, $horas, $desde, $ahora);
            // Ni el que está en pista ni el siguiente; y solo con tiempo para avisar.
            $saltar = $cola !== [] && $horas[$cola[0]->id][0] <= $ahora ? 2 : 1;
            $movibles = array_filter(array_slice($cola, $saltar), fn (PartidoEnDirecto $p): bool => $horas[$p->id][0] >= $limite);

            foreach ($movibles as $partido) {
                foreach ($pistas as $hacia) {
                    if ($hacia === $desde || ($retrasos[$hacia] ?? 0) >= $umbralMinutos) {
                        continue;
                    }

                    foreach ($this->huecos($this->cola($actuales, $horas, $hacia, $ahora), $horas, $limite) as $orden) {
                        $prueba = array_map(fn (PartidoEnDirecto $p): PartidoEnDirecto => $p->id === $partido->id ? $p->con($hacia, $p->inicio, $orden, $limite) : $p, $actuales);
                        $simulado = $this->recalcular($prueba, $ahora, $descansoMinutos, $calentamientoMinutos);
                        [$fin, $retraso] = $this->medir($prueba, $simulado);
                        $puntos = 2 * intdiv($finBase - $fin, 60) + intdiv($retrasoBase - $retraso, 60);

                        if ($puntos > $mejorPuntos) {
                            $mejorPuntos = $puntos;
                            $mejor = new SugerenciaDeRecolocacion($partido->id, $desde, $hacia, $simulado[$partido->id][0], $orden, $atrasadas[$desde], intdiv($retrasoBase - $retraso, 60), intdiv($finBase - $fin, 60));
                        }
                    }
                }
            }
        }

        return $mejor;
    }

    /**
     * Los partidos sin acabar de una pista, en su orden.
     *
     * @param  list<PartidoEnDirecto>  $partidos
     * @param  array<int, array{0: int, 1: int}>  $horas
     * @return list<PartidoEnDirecto>
     */
    private function cola(array $partidos, array $horas, int $pista, int $ahora): array
    {
        $cola = array_values(array_filter($partidos, fn (PartidoEnDirecto $p): bool => ! $p->cerrado && $p->pista === $pista && isset($horas[$p->id])));
        usort($cola, fn (PartidoEnDirecto $a, PartidoEnDirecto $b): int => $horas[$a->id][0] <=> $horas[$b->id][0]);

        return $cola;
    }

    /**
     * Dónde se puede meter un partido en la cola de una pista: delante de
     * cada partido que empieza después de $limite, o al final. Como valor
     * de orden.
     *
     * @param  list<PartidoEnDirecto>  $cola
     * @param  array<int, array{0: int, 1: int}>  $horas
     * @return list<float>
     */
    private function huecos(array $cola, array $horas, int $limite): array
    {
        $ordenes = [];
        foreach ($cola as $p) {
            if ($horas[$p->id][0] >= $limite) {
                $ordenes[] = $horas[$p->id][0] - 0.5;
            }
        }
        $ultimo = $cola === [] ? $limite : max($limite, $horas[$cola[count($cola) - 1]->id][0]);
        $ordenes[] = $ultimo + 0.5;

        return $ordenes;
    }

    /**
     * Cuándo acaba todo y cuánto retraso suman los partidos por empezar
     * (segundos sobre su hora prevista).
     *
     * @param  list<PartidoEnDirecto>  $partidos
     * @param  array<int, array{0: int, 1: int}>  $horas
     * @return array{0: int, 1: int}
     */
    private function medir(array $partidos, array $horas): array
    {
        $fin = 0;
        $retraso = 0;
        foreach ($partidos as $p) {
            if (! isset($horas[$p->id])) {
                continue;
            }
            $fin = max($fin, $horas[$p->id][1]);
            if ($p->previsto !== null) {
                $retraso += max(0, $horas[$p->id][0] - $p->previsto);
            }
        }

        return [$fin, $retraso];
    }
}
