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
 * Coloca los partidos en las pistas como una línea de tiempo continua:
 * cada uno ocupa su calentamiento más su duración estimada y el siguiente
 * de esa pista puede empezar en cuanto acaba, sin franjas fijas. Respeta
 * la disponibilidad de cada pista, que nadie juegue dos a la vez, el
 * descanso mínimo, las horas en que un jugador no puede y el orden de las
 * rondas (un partido no empieza hasta que acaban, más el descanso, los
 * que le dan sus parejas).
 *
 * Prueba varios órdenes (el natural, los largos primero y variaciones al
 * azar con semilla) y se queda con el plan que coloca más partidos, acaba
 * antes y deja menos pista parada. Nunca lanza: lo que no cabe vuelve
 * como conflicto.
 */
final readonly class PlanificadorContinuo
{
    /**
     * @param  int  $intentos  Órdenes que se prueban como mucho.
     * @param  float  $segundos  Tiempo máximo buscando (siempre se hace al menos el primero).
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
        $ordenes = $this->ordenes($partidos, $nivel);

        $mejor = null;
        $empezado = microtime(true);

        foreach ($ordenes as $n => $orden) {
            if ($n > 0 && microtime(true) - $empezado > $this->segundos) {
                break;
            }

            $resultado = $this->colocar($orden, $base, $restricciones);

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
     * @param  array<int, list<Intervalo>>  $pistas
     * @param  list<OcupacionFija>  $fijos
     * @return array{tramos: array<int, list<array{0: int, 1: int}>>, pista: array<int, list<array{0: int, 1: int}>>, jugador: array<int, list<array{0: int, 1: int}>>, noPuede: array<int, list<array{0: int, 1: int}>>, fin: array<int, int>}
     */
    private function estadoInicial(array $pistas, array $fijos, RestriccionesDePlanificacion $restricciones): array
    {
        $estado = ['tramos' => [], 'pista' => [], 'jugador' => [], 'noPuede' => [], 'fin' => []];

        foreach ($pistas as $pista => $tramos) {
            $lista = array_map(fn (Intervalo $t): array => [$t->desde->getTimestamp(), $t->hasta->getTimestamp()], $tramos);
            usort($lista, fn (array $a, array $b): int => $a[0] <=> $b[0]);
            $estado['tramos'][$pista] = $lista;
            $estado['pista'][$pista] = [];
        }

        foreach ($fijos as $fijo) {
            $intervalo = [$fijo->intervalo->desde->getTimestamp(), $fijo->intervalo->hasta->getTimestamp()];
            $estado['pista'][$fijo->pista][] = $intervalo;
            foreach ($fijo->jugadores as $jugador) {
                $estado['jugador'][$jugador][] = $intervalo;
            }
            if ($fijo->partido !== null) {
                $estado['fin'][$fijo->partido] = $intervalo[1];
            }
        }

        foreach ($restricciones->noDisponibilidades as $jugador => $intervalos) {
            $estado['noPuede'][$jugador] = array_map(fn (Intervalo $i): array => [$i->desde->getTimestamp(), $i->hasta->getTimestamp()], $intervalos);
        }

        return $estado;
    }

    /**
     * Cuántos partidos pendientes tiene cada uno por delante (0: no depende
     * de ninguno pendiente), para colocar siempre antes a los que van antes.
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
     * Los órdenes a probar: el natural (nivel, ronda, jornada, categoría),
     * los más largos primero y variaciones al azar dentro de cada nivel.
     *
     * @param  list<PartidoAPlanificar>  $partidos
     * @param  array<int, int>  $nivel
     * @return list<list<PartidoAPlanificar>>
     */
    private function ordenes(array $partidos, array $nivel): array
    {
        $natural = fn (PartidoAPlanificar $p, float $ruido = 0.0): array => [
            $nivel[$p->id] ?? 0,
            ($p->ronda ?? 0) * 1000 + ($p->jornada ?? 0) * 100 + min($p->prioridadDeCategoria, 99) + $ruido,
            $p->id,
        ];

        $ordenar = function (callable $clave) use ($partidos): array {
            $copia = $partidos;
            $claves = [];
            foreach ($copia as $p) {
                $claves[$p->id] = $clave($p);
            }
            usort($copia, fn (PartidoAPlanificar $a, PartidoAPlanificar $b): int => $claves[$a->id] <=> $claves[$b->id]);

            return $copia;
        };

        $ordenes = [
            $ordenar(fn (PartidoAPlanificar $p): array => $natural($p)),
            $ordenar(fn (PartidoAPlanificar $p): array => [$nivel[$p->id] ?? 0, -$p->duracionMinutos, ...array_slice($natural($p), 1)]),
        ];

        mt_srand($this->semilla);
        for ($i = 2; $i < $this->intentos; $i++) {
            // Del orden natural a mover partidos hasta una jornada y media.
            $amplitud = 30 + ($i % 5) * 30;
            $ruidos = [];
            foreach ($partidos as $p) {
                $ruidos[$p->id] = mt_rand(0, $amplitud * 10) / 10;
            }
            $ordenes[] = $ordenar(fn (PartidoAPlanificar $p): array => $natural($p, $ruidos[$p->id]));
        }

        return $ordenes;
    }

    /**
     * Coloca los partidos en este orden, cada uno en la pista y hora más
     * temprana en que cabe (también en un hueco anterior que haya quedado).
     *
     * @param  list<PartidoAPlanificar>  $orden
     * @param  array{tramos: array<int, list<array{0: int, 1: int}>>, pista: array<int, list<array{0: int, 1: int}>>, jugador: array<int, list<array{0: int, 1: int}>>, noPuede: array<int, list<array{0: int, 1: int}>>, fin: array<int, int>}  $estado
     * @return array{colocados: array<int, array{0: int, 1: int, 2: int}>, conflictos: list<Conflicto>, puntuacion: array{0: int, 1: int, 2: int}}
     */
    private function colocar(array $orden, array $estado, RestriccionesDePlanificacion $restricciones): array
    {
        $descanso = $restricciones->descansoMinimoMinutos * 60;
        $minimo = $restricciones->noAntesDe?->getTimestamp() ?? PHP_INT_MIN;
        $colocados = [];
        $conflictos = [];

        foreach ($orden as $partido) {
            $desde = $minimo;
            $pendiente = null;

            foreach ($partido->dependeDe as $previo) {
                if (! isset($estado['fin'][$previo])) {
                    $pendiente = $previo;

                    break;
                }
                $desde = max($desde, $estado['fin'][$previo] + $descanso);
            }

            if ($pendiente !== null) {
                $conflictos[] = new Conflicto($partido->id, Conflicto::ORDEN_DE_RONDAS, "El partido {$pendiente} tiene que jugarse antes y no tiene hora.");

                continue;
            }

            $largo = ($restricciones->calentamientoMinutos + $partido->duracionMinutos) * 60;
            $elegido = null;

            foreach (array_keys($estado['tramos']) as $pista) {
                $inicio = $this->primerHueco($pista, $desde, $largo, $partido->jugadores, $estado, $descanso, $elegido[1] ?? null);
                if ($inicio !== null && ($elegido === null || $inicio < $elegido[1])) {
                    $elegido = [$pista, $inicio];
                }
            }

            if ($elegido === null) {
                $conflictos[] = new Conflicto($partido->id, Conflicto::SIN_FRANJA, 'No queda hueco en ninguna pista con su calentamiento, sin solapar a sus jugadores y con su descanso.');

                continue;
            }

            [$pista, $inicio] = $elegido;
            $intervalo = [$inicio, $inicio + $largo];
            $estado['pista'][$pista][] = $intervalo;
            foreach ($partido->jugadores as $jugador) {
                $estado['jugador'][$jugador][] = $intervalo;
            }
            $estado['fin'][$partido->id] = $intervalo[1];
            $colocados[$partido->id] = [$pista, $intervalo[0], $intervalo[1]];
        }

        $fin = $colocados === [] ? 0 : max(array_map(fn (array $c): int => $c[2], $colocados));

        return [
            'colocados' => $colocados,
            'conflictos' => $conflictos,
            'puntuacion' => [count($conflictos), $fin, $this->muertos($colocados, $estado['tramos'])],
        ];
    }

    /**
     * La hora más temprana desde $desde en que el partido cabe en esta
     * pista, o null. Solo se miran los instantes en que algo deja de
     * estorbar: el propio $desde, el inicio de un tramo, el final de otro
     * partido de la pista y el final de lo que ocupa a sus jugadores (más
     * el descanso). Con $antesDe, no se busca más allá.
     *
     * @param  list<int>  $jugadores
     * @param  array{tramos: array<int, list<array{0: int, 1: int}>>, pista: array<int, list<array{0: int, 1: int}>>, jugador: array<int, list<array{0: int, 1: int}>>, noPuede: array<int, list<array{0: int, 1: int}>>, fin: array<int, int>}  $estado
     */
    private function primerHueco(int $pista, int $desde, int $largo, array $jugadores, array $estado, int $descanso, ?int $antesDe): ?int
    {
        $tramos = $estado['tramos'][$pista];
        $ocupada = $estado['pista'][$pista] ?? [];

        $candidatos = [$desde];
        foreach ($tramos as [$a]) {
            $candidatos[] = $a;
        }
        foreach ($ocupada as [, $b]) {
            $candidatos[] = $b;
        }
        foreach ($jugadores as $jugador) {
            foreach ($estado['jugador'][$jugador] ?? [] as [, $b]) {
                $candidatos[] = $b + $descanso;
            }
            foreach ($estado['noPuede'][$jugador] ?? [] as [, $b]) {
                $candidatos[] = $b;
            }
        }

        $candidatos = array_values(array_unique(array_filter($candidatos, fn (int $t): bool => $t >= $desde)));
        sort($candidatos);

        foreach ($candidatos as $inicio) {
            if ($antesDe !== null && $inicio >= $antesDe) {
                return null;
            }

            $fin = $inicio + $largo;

            if (! $this->dentroDeUnTramo($inicio, $fin, $tramos) || $this->solapa($inicio, $fin, $ocupada, 0)) {
                continue;
            }

            foreach ($jugadores as $jugador) {
                if ($this->solapa($inicio, $fin, $estado['jugador'][$jugador] ?? [], $descanso) || $this->solapa($inicio, $fin, $estado['noPuede'][$jugador] ?? [], 0)) {
                    continue 2;
                }
            }

            return $inicio;
        }

        return null;
    }

    /** @param  list<array{0: int, 1: int}>  $tramos */
    private function dentroDeUnTramo(int $inicio, int $fin, array $tramos): bool
    {
        foreach ($tramos as [$a, $b]) {
            if ($inicio >= $a && $fin <= $b) {
                return true;
            }
        }

        return false;
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

        return new PlanDePistas(
            $colocados,
            $resultado['conflictos'],
            $resultado['colocados'] === [] ? null : (new DateTimeImmutable)->setTimestamp($resultado['puntuacion'][1]),
            intdiv(max(0, $disponible - $ocupado), 60),
            $disponible > 0 ? (int) round(100 * $ocupado / $disponible) : 0,
        );
    }
}
