<?php

namespace App\Competicion;

use App\Competicion\Datos\Conflicto;
use App\Competicion\Datos\Intervalo;
use App\Competicion\Datos\OcupacionFija;
use App\Competicion\Datos\PartidoAPlanificar;
use App\Competicion\Datos\PartidoColocado;
use App\Competicion\Datos\PlanDePistas;
use App\Competicion\Datos\RestriccionesDePlanificacion;
use DateTimeImmutable;

/**
 * Coloca los partidos en las pistas como una línea de tiempo continua,
 * pista a pista: en cuanto una pista queda libre entra en ella el mejor
 * partido que se puede jugar en ese momento, así que solo queda parada si
 * no hay ninguno posible. Cada partido ocupa su calentamiento más su
 * duración estimada; quien viene de jugar no calienta (acaba su partido
 * mientras el rival calienta solo).
 *
 * Siempre: nadie juega dos a la vez, nadie juega tres seguidos, se
 * respetan la disponibilidad de cada pista, el descanso mínimo (si lo hay),
 * las horas en que un jugador no puede y el orden de las rondas (un partido
 * no empieza hasta que acaban los que le dan sus parejas). Los de cuadro
 * no van a pistas exteriores mientras haya cubiertas.
 *
 * Si se puede: no jugar dos seguidos, que cada categoría repita pistas
 * (sin pasar de las que le tocan por lo que juega), que en grupos todas
 * las parejas jueguen alguna vez fuera, y que cada categoría vaya jornada
 * a jornada.
 *
 * Prueba varias veces con un poco de azar en las preferencias y se queda
 * con el plan que coloca más partidos, acaba antes y cumple mejor las
 * preferencias. Nunca lanza: lo que no cabe vuelve como conflicto.
 */
final readonly class PlanificadorContinuo
{
    /** Menos de esto sin jugar entre un partido y el siguiente cuenta como jugarlos seguidos. */
    public const MINUTOS_PARA_DESCANSAR = 20;

    /**
     * @param  int  $intentos  Pruebas como mucho.
     * @param  float  $segundos  Tiempo máximo buscando (siempre se hace al menos la primera).
     */
    public function __construct(
        private int $intentos = 40,
        private float $segundos = 4.0,
        private int $semilla = 1,
    ) {}

    /**
     * @param  list<PartidoAPlanificar>  $partidos
     * @param  array<int, list<Intervalo>>  $pistas  Pista → tramos en que está disponible.
     * @param  list<OcupacionFija>  $fijos  Lo que ya ocupa pista (y jugadores).
     */
    public function planificar(array $partidos, array $pistas, RestriccionesDePlanificacion $restricciones, array $fijos = []): PlanDePistas
    {
        $base = $this->estadoInicial($pistas, $fijos, $restricciones);
        $nivel = $this->niveles($partidos);

        // En orden natural: las rondas de antes, primero.
        usort($partidos, fn (PartidoAPlanificar $a, PartidoAPlanificar $b): int => [$nivel[$a->id] ?? 0, $a->ronda ?? 0, $a->jornada ?? 0, $a->prioridadDeCategoria, $a->id] <=> [$nivel[$b->id] ?? 0, $b->ronda ?? 0, $b->jornada ?? 0, $b->prioridadDeCategoria, $b->id]);

        $mejor = null;
        $empezado = microtime(true);
        mt_srand($this->semilla);

        for ($n = 0; $n < max(1, $this->intentos); $n++) {
            if ($n > 0 && microtime(true) - $empezado > $this->segundos) {
                break;
            }

            // La primera sin azar; luego, cada vez un poco más.
            $ruido = [];
            foreach ($partidos as $partido) {
                $ruido[$partido->id] = $n === 0 ? 0.0 : mt_rand(0, (3 + ($n % 4) * 2) * 10) / 10;
            }

            $resultado = $this->colocar($partidos, $base, $restricciones, $ruido);

            if ($mejor === null || $resultado['puntuacion'] < $mejor['puntuacion']) {
                $mejor = $resultado;
            }
        }

        if ($mejor === null) {
            return new PlanDePistas([]);
        }

        return $this->plan($mejor, $base, $partidos);
    }

    /**
     * Las pistas con sus tramos y lo que ya está ocupado. A los jugadores
     * de lo ya fijado se les cuenta el juego, no el calentamiento.
     *
     * @param  array<int, list<Intervalo>>  $pistas
     * @param  list<OcupacionFija>  $fijos
     * @return array{tramos: array<int, list<array{0: int, 1: int}>>, pista: array<int, list<array{0: int, 1: int}>>, jugador: array<int, list<array{0: int, 1: int}>>, noPuede: array<int, list<array{0: int, 1: int}>>, fin: array<int, int>}
     */
    private function estadoInicial(array $pistas, array $fijos, RestriccionesDePlanificacion $restricciones): array
    {
        $estado = ['tramos' => [], 'pista' => [], 'jugador' => [], 'noPuede' => [], 'fin' => []];
        $calentamiento = $restricciones->calentamientoMinutos * 60;

        foreach ($pistas as $pista => $tramos) {
            $lista = array_map(fn (Intervalo $t): array => [$t->desde->getTimestamp(), $t->hasta->getTimestamp()], $tramos);
            usort($lista, fn (array $a, array $b): int => $a[0] <=> $b[0]);
            $estado['tramos'][$pista] = $lista;
            $estado['pista'][$pista] = [];
        }

        foreach ($fijos as $fijo) {
            $desde = $fijo->intervalo->desde->getTimestamp();
            $hasta = $fijo->intervalo->hasta->getTimestamp();
            $estado['pista'][$fijo->pista][] = [$desde, $hasta];
            foreach ($fijo->jugadores as $jugador) {
                $estado['jugador'][$jugador][] = [$hasta - $desde > $calentamiento ? $desde + $calentamiento : $desde, $hasta];
            }
            if ($fijo->partido !== null) {
                $estado['fin'][$fijo->partido] = $hasta;
            }
        }

        foreach ($restricciones->noDisponibilidades as $jugador => $intervalos) {
            $estado['noPuede'][$jugador] = array_map(fn (Intervalo $i): array => [$i->desde->getTimestamp(), $i->hasta->getTimestamp()], $intervalos);
        }

        return $estado;
    }

    /**
     * Cuántos partidos pendientes tiene cada uno por delante (0: no depende
     * de ninguno pendiente).
     *
     * @param  list<PartidoAPlanificar>  $partidos
     * @return array<int, int>
     */
    private function niveles(array $partidos): array
    {
        $porId = [];
        foreach ($partidos as $partido) {
            $porId[$partido->id] = $partido;
        }

        $nivel = array_fill_keys(array_keys($porId), 0);

        // Las dependencias van de una ronda a la siguiente: basta con repetir hasta que nada cambie.
        for ($vuelta = 0; $vuelta < count($porId); $vuelta++) {
            $cambia = false;
            foreach ($porId as $id => $partido) {
                foreach ($partido->dependeDe as $previo) {
                    if (isset($nivel[$previo]) && $nivel[$id] < $nivel[$previo] + 1) {
                        $nivel[$id] = $nivel[$previo] + 1;
                        $cambia = true;
                    }
                }
            }
            if (! $cambia) {
                break;
            }
        }

        return $nivel;
    }

    /**
     * Llena las pistas por orden de cuándo quedan libres: en cada una, el
     * partido que mejor encaja en ese momento; si no encaja ninguno, la
     * pista espera al siguiente momento en que algo cambia.
     *
     * @param  list<PartidoAPlanificar>  $partidos
     * @param  array{tramos: array<int, list<array{0: int, 1: int}>>, pista: array<int, list<array{0: int, 1: int}>>, jugador: array<int, list<array{0: int, 1: int}>>, noPuede: array<int, list<array{0: int, 1: int}>>, fin: array<int, int>}  $estado
     * @param  array<int, float>  $ruido
     * @return array{colocados: array<int, array{0: int, 1: int, 2: int}>, conflictos: list<Conflicto>, puntuacion: array{0: int, 1: int, 2: int}}
     */
    private function colocar(array $partidos, array $estado, RestriccionesDePlanificacion $restricciones, array $ruido): array
    {
        $calentamiento = $restricciones->calentamientoMinutos * 60;
        $descanso = $restricciones->descansoMinimoMinutos * 60;
        $umbral = max($descanso, self::MINUTOS_PARA_DESCANSAR * 60);
        $minimo = $restricciones->noAntesDe?->getTimestamp() ?? PHP_INT_MIN;
        $exteriores = array_fill_keys($restricciones->pistasExteriores, true);
        $hayCubiertas = array_diff(array_keys($estado['tramos']), $restricciones->pistasExteriores) !== [];

        /** @var array<int, PartidoAPlanificar> $pendientes */
        $pendientes = [];
        foreach ($partidos as $partido) {
            $pendientes[$partido->id] = $partido;
        }

        // Cuántas pistas le tocan a cada categoría por lo que juega, y cuánto le queda.
        $minutos = [];
        foreach ($partidos as $partido) {
            $minutos[$partido->categoria] = ($minutos[$partido->categoria] ?? 0) + $partido->duracionMinutos + $restricciones->calentamientoMinutos;
        }
        $total = max(1, array_sum($minutos));
        $cubiertas = max(1, count(array_diff_key($estado['tramos'], $exteriores)));
        $cuota = array_map(fn (int $m): int => max(1, (int) round($cubiertas * $m / $total)), $minutos);
        $queda = $minutos;
        $usadas = [];
        $fuera = [];

        $libre = [];
        foreach ($estado['tramos'] as $pista => $tramos) {
            $libre[$pista] = max($minimo, $tramos[0][0] ?? PHP_INT_MAX);
        }

        $colocados = [];
        $seguidos = 0;

        while ($pendientes !== [] && $libre !== []) {
            $pista = array_keys($libre, min($libre), true)[0];
            $t = $this->ajustar($pista, $libre[$pista], $estado);
            if ($t === null) {
                unset($libre[$pista]);

                continue;
            }
            if ($t !== $libre[$pista]) {
                $libre[$pista] = $t;

                continue;
            }

            $finDelTramo = $this->finDelTramo($t, $estado['tramos'][$pista]);
            $esExterior = isset($exteriores[$pista]);

            // Hasta dónde va cada categoría: la jornada (o ronda) más baja que le queda.
            $primera = [];
            foreach ($pendientes as $p) {
                $paso = $p->deGrupos ? ($p->jornada ?? 0) : 100 + ($p->ronda ?? 0);
                $primera[$p->categoria] = min($primera[$p->categoria] ?? PHP_INT_MAX, $paso);
            }
            $maximoQueda = $queda === [] ? 1 : max(1, max($queda));

            $mejor = null;
            foreach ($pendientes as $partido) {
                $inicio = $t + $calentamiento;
                $fin = $inicio + $partido->duracionMinutos * 60;

                if ($fin > $finDelTramo || $this->solapa($t, $fin, $estado['pista'][$pista], 0)) {
                    continue;
                }
                if (! $partido->deGrupos && $esExterior && $hayCubiertas) {
                    continue;
                }

                foreach ($partido->dependeDe as $previo) {
                    if (! isset($estado['fin'][$previo]) || $inicio < $estado['fin'][$previo] + $descanso) {
                        continue 2;
                    }
                }

                $conSeguido = 0;
                foreach ($partido->jugadores as $jugador) {
                    if ($this->solapa($inicio, $fin, $estado['jugador'][$jugador] ?? [], $descanso) || $this->solapa($t, $fin, $estado['noPuede'][$jugador] ?? [], 0)) {
                        continue 2;
                    }
                    $racha = $this->racha($estado['jugador'][$jugador] ?? [], $inicio, $umbral);
                    if ($racha >= 2) {
                        continue 2;
                    }
                    $conSeguido += $racha;
                }

                $paso = $partido->deGrupos ? ($partido->jornada ?? 0) : 100 + ($partido->ronda ?? 0);
                $puntos = 10 * min(3, $paso - $primera[$partido->categoria])
                    + 6 * $conSeguido
                    - 6 * $queda[$partido->categoria] / $maximoQueda
                    + $ruido[$partido->id]
                    + $partido->prioridadDeCategoria / 1000;

                if ($esExterior) {
                    // Las exteriores son de todos: van primero a las parejas de grupos que aún no han salido.
                    if ($partido->deGrupos) {
                        $sinSalir = (int) ($partido->local !== null && ! isset($fuera[$partido->local])) + (int) ($partido->visitante !== null && ! isset($fuera[$partido->visitante]));
                        $puntos += $sinSalir === 0 ? 4 : -7 * $sinSalir;
                    }
                } else {
                    // Cada categoría, en sus pistas: las que ya ha usado, sin pasar de las que le tocan.
                    $suyas = $usadas[$partido->categoria] ?? [];
                    if (isset($suyas[$pista])) {
                        $puntos -= 5;
                    } elseif (count($suyas) >= $cuota[$partido->categoria]) {
                        $puntos += 20;
                    }
                }

                if ($mejor === null || $puntos < $mejor[1]) {
                    $mejor = [$partido, $puntos, $conSeguido];
                }
            }

            if ($mejor === null) {
                $siguiente = $this->siguienteMomento($t, $pista, $pendientes, $estado, $libre, $calentamiento, $descanso, $umbral);
                if ($siguiente === null) {
                    unset($libre[$pista]);
                } else {
                    $libre[$pista] = $siguiente;
                }

                continue;
            }

            [$partido, , $conSeguido] = $mejor;
            $inicio = $t + $calentamiento;
            $fin = $inicio + $partido->duracionMinutos * 60;

            $estado['pista'][$pista][] = [$t, $fin];
            foreach ($partido->jugadores as $jugador) {
                $estado['jugador'][$jugador][] = [$inicio, $fin];
            }
            $estado['fin'][$partido->id] = $fin;
            $colocados[$partido->id] = [$pista, $t, $fin];
            if (! $esExterior) {
                $usadas[$partido->categoria][$pista] = true;
            }
            $queda[$partido->categoria] -= $partido->duracionMinutos + $restricciones->calentamientoMinutos;
            if ($esExterior && $partido->deGrupos) {
                foreach ([$partido->local, $partido->visitante] as $pareja) {
                    if ($pareja !== null) {
                        $fuera[$pareja] = true;
                    }
                }
            }
            $seguidos += $conSeguido > 0 ? 1 : 0;
            $libre[$pista] = $fin;
            unset($pendientes[$partido->id]);
        }

        $conflictos = [];
        foreach ($pendientes as $partido) {
            $sinHora = array_values(array_filter($partido->dependeDe, fn (int $previo): bool => ! isset($estado['fin'][$previo])));
            $conflictos[] = $sinHora !== []
                ? new Conflicto($partido->id, Conflicto::ORDEN_DE_RONDAS, "El partido {$sinHora[0]} tiene que jugarse antes y no tiene hora.")
                : new Conflicto($partido->id, Conflicto::SIN_FRANJA, 'No queda hueco en ninguna pista con su calentamiento, sin solapar a sus jugadores ni hacerles jugar tres seguidos.');
        }

        // Las preferencias que no se cumplen: partidos seguidos, parejas de grupos que no salen fuera y pistas de más por categoría.
        $sinSalir = 0;
        if ($exteriores !== []) {
            $parejas = [];
            foreach ($partidos as $partido) {
                if ($partido->deGrupos && isset($colocados[$partido->id])) {
                    foreach ([$partido->local, $partido->visitante] as $pareja) {
                        if ($pareja !== null) {
                            $parejas[$pareja] = true;
                        }
                    }
                }
            }
            $sinSalir = count(array_diff_key($parejas, $fuera));
        }
        $deMas = 0;
        foreach ($usadas as $categoria => $suyas) {
            $deMas += max(0, count($suyas) - $cuota[$categoria]);
        }

        $finDeTodo = $colocados === [] ? 0 : max(array_map(fn (array $c): int => $c[2], $colocados));

        return [
            'colocados' => $colocados,
            'conflictos' => $conflictos,
            'puntuacion' => [count($conflictos), intdiv($finDeTodo, 300), $this->muertos($colocados, $estado['tramos']) + 15 * $seguidos + 20 * $sinSalir + 10 * $deMas],
        ];
    }

    /**
     * El primer momento desde $t en que la pista está disponible y libre de
     * lo ya fijado, o null si ya no lo está más.
     *
     * @param  array{tramos: array<int, list<array{0: int, 1: int}>>, pista: array<int, list<array{0: int, 1: int}>>, jugador: array<int, list<array{0: int, 1: int}>>, noPuede: array<int, list<array{0: int, 1: int}>>, fin: array<int, int>}  $estado
     */
    private function ajustar(int $pista, int $t, array $estado): ?int
    {
        do {
            $antes = $t;
            $dentro = false;
            foreach ($estado['tramos'][$pista] as [$a, $b]) {
                if ($t >= $a && $t < $b) {
                    $dentro = true;

                    break;
                }
                if ($a > $t) {
                    $t = $a;
                    $dentro = true;

                    break;
                }
            }
            if (! $dentro) {
                return null;
            }
            foreach ($estado['pista'][$pista] as [$a, $b]) {
                if ($t >= $a && $t < $b) {
                    $t = $b;
                }
            }
        } while ($t !== $antes);

        return $t;
    }

    /** @param  list<array{0: int, 1: int}>  $tramos */
    private function finDelTramo(int $t, array $tramos): int
    {
        foreach ($tramos as [$a, $b]) {
            if ($t >= $a && $t < $b) {
                return $b;
            }
        }

        return $t;
    }

    /**
     * Cuántos partidos lleva seguidos un jugador si empieza a jugar en
     * $inicio: los que acaban menos de $umbral antes, encadenados.
     *
     * @param  list<array{0: int, 1: int}>  $intervalos
     */
    private function racha(array $intervalos, int $inicio, int $umbral): int
    {
        $previos = array_values(array_filter($intervalos, fn (array $i): bool => $i[1] <= $inicio));
        usort($previos, fn (array $a, array $b): int => $b[1] <=> $a[1]);

        $racha = 0;
        $desde = $inicio;
        foreach ($previos as [$a, $b]) {
            if ($desde - $b >= $umbral) {
                break;
            }
            $racha++;
            $desde = $a;
        }

        return $racha;
    }

    /**
     * Lo siguiente que puede dejar entrar un partido en esta pista: que
     * acabe otro, que un jugador quede libre (o descansado), que acabe lo
     * que le impide jugar o que empiece otro tramo.
     *
     * @param  array<int, PartidoAPlanificar>  $pendientes
     * @param  array{tramos: array<int, list<array{0: int, 1: int}>>, pista: array<int, list<array{0: int, 1: int}>>, jugador: array<int, list<array{0: int, 1: int}>>, noPuede: array<int, list<array{0: int, 1: int}>>, fin: array<int, int>}  $estado
     * @param  array<int, int>  $libre
     */
    private function siguienteMomento(int $t, int $pista, array $pendientes, array $estado, array $libre, int $calentamiento, int $descanso, int $umbral): ?int
    {
        $momentos = [];
        foreach ($libre as $otra => $cuando) {
            if ($otra !== $pista) {
                $momentos[] = $cuando;
            }
        }
        foreach ($estado['pista'][$pista] as [, $b]) {
            $momentos[] = $b;
        }
        foreach ($estado['tramos'][$pista] as [$a]) {
            $momentos[] = $a;
        }
        foreach ($pendientes as $partido) {
            foreach ($partido->dependeDe as $previo) {
                if (isset($estado['fin'][$previo])) {
                    $momentos[] = $estado['fin'][$previo] + $descanso - $calentamiento;
                }
            }
            foreach ($partido->jugadores as $jugador) {
                foreach ($estado['jugador'][$jugador] ?? [] as [, $b]) {
                    $momentos[] = $b + $descanso - $calentamiento;
                    $momentos[] = $b + $umbral - $calentamiento;
                }
                foreach ($estado['noPuede'][$jugador] ?? [] as [, $b]) {
                    $momentos[] = $b;
                }
            }
        }

        $despues = array_filter($momentos, fn (int $m): bool => $m > $t);

        return $despues === [] ? null : min($despues);
    }

    /**
     * Si [inicio, fin) choca con algún intervalo, dejando $margen segundos
     * por cada lado.
     *
     * @param  list<array{0: int, 1: int}>  $intervalos
     */
    private function solapa(int $inicio, int $fin, array $intervalos, int $margen): bool
    {
        foreach ($intervalos as [$a, $b]) {
            if ($inicio < $b + $margen && $a < $fin + $margen) {
                return true;
            }
        }

        return false;
    }

    /**
     * Los minutos en que alguna pista está disponible y parada entre el
     * primer partido colocado y el último.
     *
     * @param  array<int, array{0: int, 1: int, 2: int}>  $colocados
     * @param  array<int, list<array{0: int, 1: int}>>  $tramos
     */
    private function muertos(array $colocados, array $tramos): int
    {
        [$disponible, $ocupado] = $this->aprovechamiento($colocados, $tramos);

        return intdiv(max(0, $disponible - $ocupado), 60);
    }

    /**
     * Segundos disponibles y ocupados por lo colocado, entre el primer
     * partido colocado y el último.
     *
     * @param  array<int, array{0: int, 1: int, 2: int}>  $colocados
     * @param  array<int, list<array{0: int, 1: int}>>  $tramos
     * @return array{0: int, 1: int}
     */
    private function aprovechamiento(array $colocados, array $tramos): array
    {
        if ($colocados === []) {
            return [0, 0];
        }

        $desde = min(array_map(fn (array $c): int => $c[1], $colocados));
        $hasta = max(array_map(fn (array $c): int => $c[2], $colocados));
        $disponible = 0;

        foreach ($tramos as $lista) {
            foreach ($lista as [$a, $b]) {
                $disponible += max(0, min($b, $hasta) - max($a, $desde));
            }
        }

        $ocupado = array_sum(array_map(fn (array $c): int => $c[2] - $c[1], $colocados));

        return [$disponible, $ocupado];
    }

    /**
     * @param  array{colocados: array<int, array{0: int, 1: int, 2: int}>, conflictos: list<Conflicto>, puntuacion: array{0: int, 1: int, 2: int}}  $resultado
     * @param  array{tramos: array<int, list<array{0: int, 1: int}>>, pista: array<int, list<array{0: int, 1: int}>>, jugador: array<int, list<array{0: int, 1: int}>>, noPuede: array<int, list<array{0: int, 1: int}>>, fin: array<int, int>}  $base
     * @param  list<PartidoAPlanificar>  $partidos
     */
    private function plan(array $resultado, array $base, array $partidos): PlanDePistas
    {
        $colocados = [];
        foreach ($partidos as $partido) {
            if (isset($resultado['colocados'][$partido->id])) {
                [$pista, $inicio, $fin] = $resultado['colocados'][$partido->id];
                $colocados[] = new PartidoColocado($partido->id, $pista, (new DateTimeImmutable)->setTimestamp($inicio), (new DateTimeImmutable)->setTimestamp($fin));
            }
        }

        usort($colocados, fn (PartidoColocado $a, PartidoColocado $b): int => [$a->inicio, $a->pista] <=> [$b->inicio, $b->pista]);

        [$disponible, $ocupado] = $this->aprovechamiento($resultado['colocados'], $base['tramos']);
        $fin = $resultado['colocados'] === [] ? null : max(array_map(fn (array $c): int => $c[2], $resultado['colocados']));

        return new PlanDePistas(
            $colocados,
            $resultado['conflictos'],
            $fin === null ? null : (new DateTimeImmutable)->setTimestamp($fin),
            intdiv(max(0, $disponible - $ocupado), 60),
            $disponible > 0 ? (int) round(100 * $ocupado / $disponible) : 0,
        );
    }
}
