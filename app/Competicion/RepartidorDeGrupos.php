<?php

namespace App\Competicion;

use App\Competicion\Datos\ConfiguracionDeGrupos;
use App\Competicion\Datos\Grupo;
use App\Competicion\Datos\Participante;
use App\Competicion\Datos\RepartoDeGrupos;
use App\Competicion\Errores\ConfiguracionInvalida;
use App\Competicion\Errores\ParticipanteDuplicado;
use App\Enums\ModoDeSorteo;

/**
 * Reparte participantes en grupos: tamaños equilibrados, primero los que
 * el organizador fija a mano en cada grupo, luego las cabezas de serie en
 * serpiente y el resto al azar con semilla (el mismo sorteo se repite), o
 * todo manual (solo valida). Lo que es decisión del organizador vuelve
 * como aviso.
 */
final readonly class RepartidorDeGrupos
{
    /**
     * @param  list<Participante>  $participantes
     */
    public function repartir(array $participantes, ConfiguracionDeGrupos $configuracion): RepartoDeGrupos
    {
        $ids = [];
        foreach ($participantes as $participante) {
            if (in_array($participante->id, $ids, true)) {
                throw new ParticipanteDuplicado($participante->id);
            }
            $ids[] = $participante->id;
        }

        if ($configuracion->modoDeSorteo === ModoDeSorteo::Manual) {
            return $this->validarManual($ids, $configuracion);
        }

        $n = count($ids);

        if ($n === 0) {
            throw new ConfiguracionInvalida(['No hay participantes que repartir.']);
        }

        $tamanos = $this->tamanos($n, $configuracion);
        $semilla = $configuracion->semilla ?? random_int(1, PHP_INT_MAX >> 1);
        $grupos = array_fill(0, count($tamanos), []);
        $colocados = [];

        $this->colocarFijos($ids, $configuracion->gruposManuales ?? [], $tamanos, $grupos, $colocados);

        if ($configuracion->modoDeSorteo === ModoDeSorteo::CabezasDeSerie) {
            $this->colocarCabezas($participantes, $tamanos, $grupos, $colocados);
        }

        $this->colocarRepartidos($ids, $configuracion->repartidos, $tamanos, $grupos, $colocados, $semilla);

        $resto = $this->barajar(array_values(array_filter($ids, fn (int $id): bool => ! in_array($id, $colocados, true))), $semilla);

        foreach ($resto as $id) {
            foreach ($grupos as $i => $grupo) {
                if (count($grupo) < $tamanos[$i]) {
                    $grupos[$i][] = $id;

                    break;
                }
            }
        }

        $avisos = [];
        $distintos = array_values(array_unique($tamanos));
        sort($distintos);

        if (count($distintos) > 1) {
            $avisos[] = 'Quedan grupos de distinto tamaño: de '.implode(' y de ', $distintos).'.';
        }
        if ($tamanos !== [] && min($tamanos) < 3) {
            $avisos[] = 'Hay grupos de menos de 3 participantes.';
        }

        return new RepartoDeGrupos(
            array_map(fn (int $i, array $miembros): Grupo => new Grupo(Grupo::nombrePorOrden($i + 1), $i + 1, $miembros), array_keys($grupos), $grupos),
            $avisos,
            $semilla,
        );
    }

    /**
     * Coloca solo una tanda: respeta lo ya fijado en cada grupo
     * (gruposManuales) y reparte al azar las parejas de la tanda
     * (repartidos), una por grupo; no sortea a nadie más. Para hacer el
     * sorteo por bombos, tanda a tanda.
     *
     * @param  list<Participante>  $participantes
     *
     * @throws ConfiguracionInvalida
     * @throws ParticipanteDuplicado
     */
    public function colocarTanda(array $participantes, ConfiguracionDeGrupos $configuracion): RepartoDeGrupos
    {
        $ids = [];
        foreach ($participantes as $participante) {
            if (in_array($participante->id, $ids, true)) {
                throw new ParticipanteDuplicado($participante->id);
            }
            $ids[] = $participante->id;
        }

        if ($ids === []) {
            throw new ConfiguracionInvalida(['No hay participantes que repartir.']);
        }

        $tamanos = $this->tamanos(count($ids), $configuracion);
        $semilla = $configuracion->semilla ?? random_int(1, PHP_INT_MAX >> 1);
        $grupos = array_fill(0, count($tamanos), []);
        $colocados = [];

        // Lo ya colocado se respeta tal cual, aunque a mano se haya llenado un grupo de más.
        foreach ($configuracion->gruposManuales ?? [] as $i => $miembros) {
            if ($miembros === []) {
                continue;
            }
            if (! isset($tamanos[$i])) {
                throw new ConfiguracionInvalida(['Salen '.count($tamanos).' grupos: no hay grupo '.Grupo::nombrePorOrden($i + 1).'.']);
            }
            foreach ($miembros as $id) {
                if (! in_array($id, $ids, true)) {
                    throw new ConfiguracionInvalida(["El participante {$id} no está en esta fase (¿no está confirmado?)."]);
                }
                if (in_array($id, $colocados, true)) {
                    throw new ParticipanteDuplicado($id);
                }
                $grupos[$i][] = $id;
                $colocados[] = $id;
            }
            $tamanos[$i] = max($tamanos[$i], count($grupos[$i]));
        }

        $this->colocarRepartidos($ids, $configuracion->repartidos, $tamanos, $grupos, $colocados, $semilla);

        return new RepartoDeGrupos(
            array_map(fn (int $i, array $miembros): Grupo => new Grupo(Grupo::nombrePorOrden($i + 1), $i + 1, $miembros), array_keys($grupos), $grupos),
            [],
            $semilla,
        );
    }

    /**
     * Tamaños equilibrados: `n` en `g` grupos, los primeros `n mod g` con
     * uno más. Por tamaño `t`, se hacen `n div t` grupos y el resto se
     * reparte entre los primeros (13 en tamaño 4: 5, 4 y 4).
     *
     * @return list<int>
     */
    private function tamanos(int $n, ConfiguracionDeGrupos $configuracion): array
    {
        if ($configuracion->numeroDeGrupos !== null) {
            $g = min($configuracion->numeroDeGrupos, $n);
        } else {
            $t = $configuracion->tamanoDeGrupo ?? 4;
            $g = max(1, intdiv($n, $t));
        }

        $base = intdiv($n, $g);
        $sobran = $n % $g;

        return array_map(fn (int $i): int => $base + ($i < $sobran ? 1 : 0), range(0, $g - 1));
    }

    /**
     * Las cabezas de serie, por su número, en serpiente: 1→A, 2→B, …,
     * g→última, g+1→última, g+2→penúltima…
     *
     * @param  list<Participante>  $participantes
     * @param  list<int>  $tamanos
     * @param  array<int, list<int>>  $grupos
     * @param  list<int>  $colocados
     */
    private function colocarCabezas(array $participantes, array $tamanos, array &$grupos, array &$colocados): void
    {
        $cabezas = array_values(array_filter($participantes, fn (Participante $p): bool => $p->cabezaDeSerie !== null && ! in_array($p->id, $colocados, true)));
        usort($cabezas, fn (Participante $a, Participante $b): int => ($a->cabezaDeSerie <=> $b->cabezaDeSerie) ?: ($a->id <=> $b->id));

        $g = count($tamanos);

        foreach ($cabezas as $k => $cabeza) {
            $vuelta = intdiv($k, $g);
            $posicion = $k % $g;
            $grupo = $vuelta % 2 === 0 ? $posicion : $g - 1 - $posicion;

            // Si ese grupo ya está lleno (más cabezas que sitios), al siguiente con hueco.
            for ($intentos = 0; $intentos < $g && count($grupos[$grupo]) >= $tamanos[$grupo]; $intentos++) {
                $grupo = ($grupo + 1) % $g;
            }

            $grupos[$grupo][] = $cabeza->id;
            $colocados[] = $cabeza->id;
        }
    }

    /**
     * Los que el organizador fija a mano, cada uno en su grupo, antes de
     * sortear al resto. Un grupo no admite más de los que le tocan.
     *
     * @param  list<int>  $ids
     * @param  list<list<int>>  $fijos
     * @param  list<int>  $tamanos
     * @param  array<int, list<int>>  $grupos
     * @param  list<int>  $colocados
     */
    private function colocarFijos(array $ids, array $fijos, array $tamanos, array &$grupos, array &$colocados): void
    {
        foreach ($fijos as $i => $miembros) {
            if ($miembros === []) {
                continue;
            }

            $nombre = Grupo::nombrePorOrden($i + 1);
            if (! isset($tamanos[$i])) {
                throw new ConfiguracionInvalida(['Salen '.count($tamanos)." grupos: no hay grupo {$nombre} donde fijar parejas."]);
            }

            foreach ($miembros as $id) {
                if (! in_array($id, $ids, true)) {
                    throw new ConfiguracionInvalida(["El participante {$id} no está en esta fase (¿no está confirmado?)."]);
                }
                if (in_array($id, $colocados, true)) {
                    throw new ParticipanteDuplicado($id);
                }
                $grupos[$i][] = $id;
                $colocados[] = $id;
            }

            if (count($grupos[$i]) > $tamanos[$i]) {
                throw new ConfiguracionInvalida(["En el grupo {$nombre} caben {$tamanos[$i]} parejas y se han fijado ".count($grupos[$i]).'.']);
            }
        }
    }

    /**
     * Los que se reparten al azar entre los grupos: en orden aleatorio,
     * cada uno al grupo con hueco que menos de ellos tiene (si empatan, al
     * que menos parejas tiene en total, y si aún empatan, uno al azar). Así
     * salen uno por grupo y, si hay más que grupos, lo más equilibrado
     * posible. Los ya colocados (fijados o cabezas de serie) se saltan.
     *
     * @param  list<int>  $ids
     * @param  list<int>  $repartidos
     * @param  list<int>  $tamanos
     * @param  array<int, list<int>>  $grupos
     * @param  list<int>  $colocados
     */
    private function colocarRepartidos(array $ids, array $repartidos, array $tamanos, array &$grupos, array &$colocados, int $semilla): void
    {
        foreach ($repartidos as $id) {
            if (! in_array($id, $ids, true)) {
                throw new ConfiguracionInvalida(["El participante {$id} no está en esta fase (¿no está confirmado?)."]);
            }
        }

        $lista = $this->barajar(array_values(array_filter($repartidos, fn (int $id): bool => ! in_array($id, $colocados, true))), $semilla + 1);
        $cuantos = array_fill(0, count($tamanos), 0);

        foreach ($lista as $id) {
            $conHueco = array_keys(array_filter($tamanos, fn (int $tamano, int $i): bool => count($grupos[$i]) < $tamano, ARRAY_FILTER_USE_BOTH));
            if ($conHueco === []) {
                return;
            }

            $clave = fn (int $i): int => $cuantos[$i] * 1000 + count($grupos[$i]);
            $minimo = min(array_map($clave, $conHueco));
            $empatados = array_values(array_filter($conHueco, fn (int $i): bool => $clave($i) === $minimo));
            $grupo = $empatados[mt_rand(0, count($empatados) - 1)];

            $grupos[$grupo][] = $id;
            $colocados[] = $id;
            $cuantos[$grupo]++;
        }
    }

    /**
     * Fisher-Yates con mt_rand sembrado: el mismo orden para la misma semilla.
     *
     * @param  list<int>  $valores
     * @return list<int>
     */
    private function barajar(array $valores, int $semilla): array
    {
        mt_srand($semilla);

        for ($i = count($valores) - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            [$valores[$i], $valores[$j]] = [$valores[$j], $valores[$i]];
        }

        return array_values($valores);
    }

    /**
     * @param  list<int>  $ids
     */
    private function validarManual(array $ids, ConfiguracionDeGrupos $configuracion): RepartoDeGrupos
    {
        $manuales = $configuracion->gruposManuales ?? [];
        $vistos = [];
        $grupos = [];

        foreach ($manuales as $i => $miembros) {
            foreach ($miembros as $id) {
                if (! in_array($id, $ids, true)) {
                    throw new ConfiguracionInvalida(["El participante {$id} no está en esta fase."]);
                }
                if (in_array($id, $vistos, true)) {
                    throw new ParticipanteDuplicado($id);
                }
                $vistos[] = $id;
            }

            $grupos[] = new Grupo(Grupo::nombrePorOrden($i + 1), $i + 1, $miembros);
        }

        $avisos = [];
        $sinGrupo = array_values(array_diff($ids, $vistos));

        if ($sinGrupo !== []) {
            $avisos[] = 'Quedan sin grupo los participantes '.implode(', ', $sinGrupo).'.';
        }

        $tamanos = array_map(fn (Grupo $g): int => $g->tamano(), $grupos);
        if ($tamanos !== [] && count(array_unique($tamanos)) > 1) {
            $avisos[] = 'Los grupos no tienen el mismo tamaño.';
        }
        if ($tamanos !== [] && min($tamanos) < 3) {
            $avisos[] = 'Hay grupos de menos de 3 participantes.';
        }

        return new RepartoDeGrupos($grupos, $avisos, $configuracion->semilla);
    }
}
