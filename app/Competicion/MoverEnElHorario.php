<?php

namespace App\Competicion;

use App\Competicion\Errores\OperacionNoPermitida;

/**
 * Lo que pasa en las pistas al mover un partido a mano (arrastrándolo en
 * la vista de horarios). Se suelta en una pista y una hora: si queda
 * cerca del final del anterior se pega a él, y los de detrás se empujan
 * si se solapan. En la pista de la que sale, los que iban pegados detrás
 * suben a ocupar el hueco. Soltado sobre otro partido, se intercambian.
 *
 * Lo que no se puede mover (jugado, en juego, bloqueos) no se toca: si
 * estorba, no se mueve nada. Después dice qué partidos dejan a alguien
 * jugando dos a la vez o tres seguidos, para avisar.
 *
 * Cada ocupación: ['id' => partido (o negativo para un bloqueo), 'pista',
 * 'inicio', 'fin' (marcas de tiempo, con el calentamiento), 'movible',
 * 'jugadores' => list<int>].
 *
 * @phpstan-type Ocupacion array{id: int, pista: int, inicio: int, fin: int, movible: bool, jugadores: list<int>}
 */
final readonly class MoverEnElHorario
{
    /** Soltado a menos de esto del final del anterior, se pega a él. */
    public const SEGUNDOS_PARA_PEGAR = 15 * 60;

    /** Entre uno y otro, esto o menos cuenta como pegados. */
    private const PEGADOS = 60;

    /**
     * @param  list<Ocupacion>  $ocupaciones
     * @return array{cambios: array<int, array{pista: int, inicio: int, fin: int}>, avisos: list<array{partido: int, tipo: string}>}
     *
     * @throws OperacionNoPermitida
     */
    public function mover(array $ocupaciones, int $partido, int $pista, int $inicio, int $calentamientoMinutos = 0): array
    {
        $porId = $this->porId($ocupaciones);
        $movido = $porId[$partido] ?? throw new OperacionNoPermitida('Ese partido no está en el horario.');
        if (! $movido['movible']) {
            throw new OperacionNoPermitida('Ese partido ya se ha jugado o está en juego: no se mueve.');
        }

        $pegados = $this->pegados($porId);
        $duracion = $movido['fin'] - $movido['inicio'];

        // Sale de su pista: los que iban pegados detrás suben.
        $nuevas = $porId;
        unset($nuevas[$partido]);
        $nuevas = $this->reajustar($nuevas, $pegados, $movido['pista'], $movido['inicio'], $movido['inicio']);

        // Entra en la otra: dentro de un partido, detrás de él; cerca del final del anterior, pegado.
        foreach ($this->dePista($nuevas, $pista) as $o) {
            if ($o['inicio'] <= $inicio && $inicio < $o['fin']) {
                $inicio = $o['fin'];

                break;
            }
        }
        $anterior = null;
        foreach ($this->dePista($nuevas, $pista) as $o) {
            if ($o['fin'] <= $inicio) {
                $anterior = $o;
            }
        }
        if ($anterior !== null && $inicio - $anterior['fin'] < self::SEGUNDOS_PARA_PEGAR) {
            $inicio = $anterior['fin'];
        }

        $nuevas[$partido] = [...$movido, 'pista' => $pista, 'inicio' => $inicio, 'fin' => $inicio + $duracion];
        $nuevas = $this->reajustar($nuevas, $pegados, $pista, $inicio, $inicio + $duracion, [$partido]);

        return $this->resultado($porId, $nuevas, $calentamientoMinutos);
    }

    /**
     * Uno ocupa el sitio del otro; si duran distinto, los de detrás de
     * cada uno se reajustan.
     *
     * @param  list<Ocupacion>  $ocupaciones
     * @return array{cambios: array<int, array{pista: int, inicio: int, fin: int}>, avisos: list<array{partido: int, tipo: string}>}
     *
     * @throws OperacionNoPermitida
     */
    public function intercambiar(array $ocupaciones, int $a, int $b, int $calentamientoMinutos = 0): array
    {
        $porId = $this->porId($ocupaciones);
        $uno = $porId[$a] ?? throw new OperacionNoPermitida('Ese partido no está en el horario.');
        $otro = $porId[$b] ?? throw new OperacionNoPermitida('Ese partido no está en el horario.');
        if (! $uno['movible'] || ! $otro['movible']) {
            throw new OperacionNoPermitida('Uno de los dos ya se ha jugado o está en juego: no se mueve.');
        }

        $pegados = $this->pegados($porId);
        $nuevas = $porId;
        $nuevas[$a] = [...$uno, 'pista' => $otro['pista'], 'inicio' => $otro['inicio'], 'fin' => $otro['inicio'] + $uno['fin'] - $uno['inicio']];
        $nuevas[$b] = [...$otro, 'pista' => $uno['pista'], 'inicio' => $uno['inicio'], 'fin' => $uno['inicio'] + $otro['fin'] - $otro['inicio']];
        $nuevas = $this->reajustar($nuevas, $pegados, $otro['pista'], $otro['inicio'], $nuevas[$a]['fin'], [$a, $b]);
        $nuevas = $this->reajustar($nuevas, $pegados, $uno['pista'], $uno['inicio'], $nuevas[$b]['fin'], [$a, $b]);

        return $this->resultado($porId, $nuevas, $calentamientoMinutos);
    }

    /**
     * Desde $desde, en la pista, los de detrás de lo que acaba en $cursor:
     * si se solapan, se empujan; si iban pegados al de antes, se pegan.
     * Para en el primero que ni se solapa ni iba pegado.
     *
     * @param  array<int, Ocupacion>  $ocupaciones
     * @param  array<int, bool>  $pegados
     * @param  list<int>  $salvo  Los que no se tocan (los que se acaban de colocar).
     * @return array<int, Ocupacion>
     *
     * @throws OperacionNoPermitida
     */
    private function reajustar(array $ocupaciones, array $pegados, int $pista, int $desde, int $cursor, array $salvo = []): array
    {
        foreach ($this->dePista($ocupaciones, $pista) as $o) {
            if (in_array($o['id'], $salvo, true) || $o['inicio'] < $desde) {
                continue;
            }

            $seSolapa = $o['inicio'] < $cursor;
            if (! $seSolapa && ! (($pegados[$o['id']] ?? false) && $o['movible'])) {
                break;
            }
            if (! $o['movible']) {
                throw new OperacionNoPermitida('No cabe: choca con un partido jugado, en juego o un bloqueo de la pista a las '.date('H:i', $o['inicio']).'.');
            }

            $duracion = $o['fin'] - $o['inicio'];
            $ocupaciones[$o['id']] = [...$o, 'inicio' => $cursor, 'fin' => $cursor + $duracion];
            $cursor += $duracion;
        }

        return $ocupaciones;
    }

    /**
     * Los que empezaban justo al acabar el anterior de su pista.
     *
     * @param  array<int, Ocupacion>  $ocupaciones
     * @return array<int, bool>
     */
    private function pegados(array $ocupaciones): array
    {
        $pegados = [];
        foreach (array_unique(array_column($ocupaciones, 'pista')) as $pista) {
            $anterior = null;
            foreach ($this->dePista($ocupaciones, (int) $pista) as $o) {
                $pegados[$o['id']] = $anterior !== null && abs($o['inicio'] - $anterior['fin']) <= self::PEGADOS;
                $anterior = $o;
            }
        }

        return $pegados;
    }

    /**
     * @param  array<int, Ocupacion>  $ocupaciones
     * @return list<Ocupacion>
     */
    private function dePista(array $ocupaciones, int $pista): array
    {
        $lista = array_values(array_filter($ocupaciones, fn (array $o): bool => $o['pista'] === $pista));
        usort($lista, fn (array $a, array $b): int => [$a['inicio'], $a['id']] <=> [$b['inicio'], $b['id']]);

        return $lista;
    }

    /**
     * @param  list<Ocupacion>  $ocupaciones
     * @return array<int, Ocupacion>
     */
    private function porId(array $ocupaciones): array
    {
        $porId = [];
        foreach ($ocupaciones as $o) {
            $porId[$o['id']] = $o;
        }

        return $porId;
    }

    /**
     * Lo que cambia y los partidos movidos que dejan a alguien jugando dos
     * a la vez o tres seguidos.
     *
     * @param  array<int, Ocupacion>  $antes
     * @param  array<int, Ocupacion>  $despues
     * @return array{cambios: array<int, array{pista: int, inicio: int, fin: int}>, avisos: list<array{partido: int, tipo: string}>}
     */
    private function resultado(array $antes, array $despues, int $calentamientoMinutos): array
    {
        $cambios = [];
        foreach ($despues as $id => $o) {
            $a = $antes[$id];
            if ($o['pista'] !== $a['pista'] || $o['inicio'] !== $a['inicio'] || $o['fin'] !== $a['fin']) {
                $cambios[$id] = ['pista' => $o['pista'], 'inicio' => $o['inicio'], 'fin' => $o['fin']];
            }
        }

        // Quien viene de jugar no calienta: cuenta el juego, no el calentamiento.
        $calentamiento = $calentamientoMinutos * 60;
        $juego = fn (array $o): array => [$calentamiento < $o['fin'] - $o['inicio'] ? $o['inicio'] + $calentamiento : $o['inicio'], $o['fin']];

        $porJugador = [];
        foreach ($despues as $o) {
            foreach ($o['jugadores'] as $jugador) {
                $porJugador[$jugador][] = [...$juego($o), $o['id']];
            }
        }

        $avisos = [];
        foreach (array_keys($cambios) as $id) {
            $o = $despues[$id];
            [$inicio, $fin] = $juego($o);
            $solapa = false;
            $tres = false;
            foreach ($o['jugadores'] as $jugador) {
                $lista = $porJugador[$jugador];
                foreach ($lista as [$a, $b, $otro]) {
                    if ($otro !== $id && $a < $fin && $inicio < $b) {
                        $solapa = true;
                    }
                }
                $tres = $tres || $this->tresSeguidos($lista);
            }
            if ($solapa) {
                $avisos[] = ['partido' => $id, 'tipo' => 'a_la_vez'];
            } elseif ($tres) {
                $avisos[] = ['partido' => $id, 'tipo' => 'tres_seguidos'];
            }
        }

        return ['cambios' => $cambios, 'avisos' => $avisos];
    }

    /** @param  list<array{0: int, 1: int, 2: int}>  $juegos */
    private function tresSeguidos(array $juegos): bool
    {
        usort($juegos, fn (array $a, array $b): int => $a[0] <=> $b[0]);
        $racha = 1;
        for ($i = 1; $i < count($juegos); $i++) {
            $racha = $juegos[$i][0] - $juegos[$i - 1][1] < PlanificadorContinuo::MINUTOS_PARA_DESCANSAR * 60 ? $racha + 1 : 1;
            if ($racha >= 3) {
                return true;
            }
        }

        return false;
    }
}
