<?php

namespace App\Competicion;

use App\Competicion\Datos\Grupo;
use App\Competicion\Datos\Partido;
use App\Competicion\Errores\NumeroDePartidosNoAlcanzable;

/**
 * Los partidos de un grupo: todos contra todos por el círculo de Berger
 * (con comodín si son impares), o un número fijo de partidos por
 * participante con la misma carga para todos. Ida y vuelta duplica
 * invirtiendo local y visitante.
 */
final readonly class GeneradorRoundRobin
{
    /**
     * @return list<Partido>
     */
    public function generar(Grupo $grupo, ?int $partidosPorParticipante = null, bool $idaYVuelta = false): array
    {
        $ids = array_values($grupo->participantes);
        $n = count($ids);

        if ($n < 2) {
            return [];
        }

        if ($partidosPorParticipante !== null && $partidosPorParticipante >= $n) {
            throw new NumeroDePartidosNoAlcanzable($n, $partidosPorParticipante);
        }

        $jornadas = $partidosPorParticipante === null || $partidosPorParticipante === $n - 1
            ? $this->circulo($ids)
            : $this->numeroFijo($ids, $partidosPorParticipante);

        $partidos = [];
        $vecesLocal = array_fill_keys($ids, 0);

        foreach ($jornadas as $jornada => $cruces) {
            foreach ($cruces as [$a, $b]) {
                // Local quien menos veces lo ha sido: nadie es siempre visitante.
                [$local, $visitante] = $vecesLocal[$a] > $vecesLocal[$b] ? [$b, $a] : [$a, $b];
                $vecesLocal[$local]++;
                $partidos[] = new Partido(grupo: $grupo->referencia(), jornada: $jornada, local: $local, visitante: $visitante);
            }
        }

        if ($idaYVuelta) {
            $total = count($jornadas);

            foreach ($partidos as $partido) {
                $partidos[] = $partido->con(jornada: $partido->jornada + $total, local: $partido->visitante, visitante: $partido->local);
            }
        }

        return $partidos;
    }

    /**
     * Quién descansa en cada jornada del todos contra todos de un grupo
     * impar: jornada → participante.
     *
     * @return array<int, int>
     */
    public function descansos(Grupo $grupo): array
    {
        $ids = array_values($grupo->participantes);

        if (count($ids) % 2 === 0) {
            return [];
        }

        $descansos = [];

        foreach ($this->rondasDeBerger($ids) as $jornada => $cruces) {
            foreach ($cruces as [$a, $b]) {
                if ($a === null) {
                    $descansos[$jornada] = $b;
                } elseif ($b === null) {
                    $descansos[$jornada] = $a;
                }
            }
        }

        return $descansos;
    }

    /**
     * Círculo de Berger: el primero fijo, los demás rotan. Jornada → cruces.
     *
     * @param  list<int>  $ids
     * @return array<int, list<array{0: int, 1: int}>>
     */
    private function circulo(array $ids): array
    {
        $jornadas = [];

        foreach ($this->rondasDeBerger($ids) as $jornada => $cruces) {
            $jornadas[$jornada] = array_values(array_filter($cruces, fn (array $cruce): bool => $cruce[0] !== null && $cruce[1] !== null));
        }

        return $jornadas;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, list<array{0: int|null, 1: int|null}>>
     */
    private function rondasDeBerger(array $ids): array
    {
        $fichas = $ids;

        if (count($fichas) % 2 === 1) {
            $fichas[] = null;
        }

        $m = count($fichas);
        $jornadas = [];

        for ($r = 0; $r < $m - 1; $r++) {
            $cruces = [];

            for ($i = 0; $i < intdiv($m, 2); $i++) {
                $a = $fichas[$i];
                $b = $fichas[$m - 1 - $i];
                // Se alterna quién es local para que nadie sea siempre visitante.
                $cruces[] = ($r + $i) % 2 === 0 ? [$a, $b] : [$b, $a];
            }

            $jornadas[$r + 1] = $cruces;

            // Rota todos menos el primero.
            $ultimo = array_pop($fichas);
            array_splice($fichas, 1, 0, [$ultimo]);
        }

        return $jornadas;
    }

    /**
     * `k` partidos por participante, con `k < n-1`. Con `n` par, las `k`
     * primeras jornadas del círculo (cada jornada da un partido a todos).
     * Con `n` impar y `k` par, cada participante juega contra los `k/2`
     * siguientes y los `k/2` anteriores en círculo. `k·n` impar no se puede.
     *
     * @param  list<int>  $ids
     * @return array<int, list<array{0: int, 1: int}>>
     */
    private function numeroFijo(array $ids, int $k): array
    {
        $n = count($ids);

        if (($k * $n) % 2 === 1) {
            throw new NumeroDePartidosNoAlcanzable($n, $k);
        }

        if ($n % 2 === 0) {
            return array_slice($this->circulo($ids), 0, $k, true);
        }

        $jornadas = [];

        for ($d = 1; $d <= intdiv($k, 2); $d++) {
            $cruces = [];

            for ($i = 0; $i < $n; $i++) {
                $j = ($i + $d) % $n;
                $cruces[] = $i % 2 === 0 ? [$ids[$i], $ids[$j]] : [$ids[$j], $ids[$i]];
            }

            $jornadas[$d] = $cruces;
        }

        return $jornadas;
    }
}
