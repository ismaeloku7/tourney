<?php

namespace App\Competicion;

use App\Competicion\Datos\Clasificado;
use App\Competicion\Datos\ConfiguracionDeEliminatoria;
use App\Competicion\Datos\Cuadro;
use App\Competicion\Datos\Origen;
use App\Competicion\Datos\Partido;
use App\Competicion\Errores\ConfiguracionInvalida;
use App\Competicion\Errores\CuadroDemasiadoPequeno;
use App\Competicion\Errores\ParticipanteDuplicado;
use App\Enums\EstadoPartido;
use App\Enums\ReglaDeCruces;

/**
 * Construye un cuadro eliminatorio a partir de clasificados ordenados:
 * tamaño potencia de 2, byes a los mejores, cabezas de serie en extremos
 * opuestos, cruces entre grupos evitando reencuentros, sorteo con semilla
 * o cruces manuales. Las rondas siguientes nacen vacías con el origen de
 * cada hueco, y el tercer puesto con los perdedores de las semifinales.
 *
 * Los ids de los partidos son provisionales (1, 2, 3… en orden de ronda y
 * posición) hasta que la aplicación los persista y reescriba los orígenes.
 */
final readonly class ConstructorDeCuadro
{
    private const INTENTOS_CONTRA_REENCUENTROS = 20000;

    public function __construct(private AvanceDeCuadro $avance = new AvanceDeCuadro) {}

    /**
     * @param  list<Clasificado>  $clasificados
     */
    public function construir(array $clasificados, ConfiguracionDeEliminatoria $configuracion): Cuadro
    {
        $n = count($clasificados);

        if ($n < 2) {
            throw new ConfiguracionInvalida(['Hacen falta al menos 2 clasificados para un cuadro.']);
        }

        $ids = array_map(fn (Clasificado $c): int => $c->participante, $clasificados);
        if (count(array_unique($ids)) !== $n) {
            throw new ParticipanteDuplicado((int) current(array_diff_assoc($ids, array_unique($ids))));
        }

        $tamano = $configuracion->tamanoDeCuadro ?? self::potenciaDeDos($n);

        if ($tamano < $n) {
            throw new CuadroDemasiadoPequeno($tamano, $n);
        }

        usort($clasificados, fn (Clasificado $a, Clasificado $b): int => $a->orden <=> $b->orden);
        $avisos = [];

        $huecos = match ($configuracion->reglaDeCruces) {
            ReglaDeCruces::Manual => $this->huecosManuales($clasificados, $tamano, $configuracion),
            ReglaDeCruces::Sorteo => $this->huecosPorSorteo($clasificados, $tamano, $configuracion),
            ReglaDeCruces::EntreGrupos => $this->huecosEntreGrupos($clasificados, $tamano, $configuracion, $avisos),
        };

        return $this->desdeHuecos($huecos, $configuracion, $avisos);
    }

    /**
     * Un cuadro a partir de los huecos de la primera ronda, en orden: un id
     * de participante, un Origen (de dónde saldrá) o nulo (bye).
     *
     * @param  list<int|Origen|null>  $huecos
     * @param  list<string>  $avisos
     */
    public function desdeHuecos(array $huecos, ConfiguracionDeEliminatoria $configuracion, array $avisos = []): Cuadro
    {
        $tamano = count($huecos);

        if ($tamano < 2 || ($tamano & ($tamano - 1)) !== 0) {
            throw new ConfiguracionInvalida(['El número de huecos tiene que ser una potencia de 2.']);
        }

        $rondas = (int) log($tamano, 2);
        $partidos = [];
        $id = 1;
        $anterior = [];

        for ($p = 1; $p <= $tamano / 2; $p++) {
            $local = $huecos[2 * $p - 2];
            $visitante = $huecos[2 * $p - 1];

            $partidos[] = new Partido(
                id: $id,
                ronda: 1,
                posicion: $p,
                local: is_int($local) ? $local : null,
                visitante: is_int($visitante) ? $visitante : null,
                origenLocal: $local instanceof Origen ? $local : null,
                origenVisitante: $visitante instanceof Origen ? $visitante : null,
            );
            $anterior[$p] = $id++;
        }

        for ($ronda = 2; $ronda <= $rondas; $ronda++) {
            $actual = [];

            for ($p = 1; $p <= $tamano / (2 ** $ronda); $p++) {
                $partidos[] = new Partido(
                    id: $id,
                    ronda: $ronda,
                    posicion: $p,
                    origenLocal: Origen::ganadorDe($anterior[2 * $p - 1]),
                    origenVisitante: Origen::ganadorDe($anterior[2 * $p]),
                );
                $actual[$p] = $id++;
            }

            if ($ronda === $rondas && $configuracion->tercerYCuartoPuesto && $rondas >= 2) {
                $partidos[] = new Partido(
                    id: $id++,
                    ronda: $ronda,
                    posicion: 2,
                    esTercerPuesto: true,
                    origenLocal: Origen::perdedorDe($anterior[1]),
                    origenVisitante: Origen::perdedorDe($anterior[2]),
                );
            }

            $anterior = $actual;
        }

        $cuadro = new Cuadro($tamano, $rondas, $partidos, $avisos);

        // Los byes de la primera ronda cierran su partido y avanzan solos.
        foreach ($cuadro->partidosDeRonda(1) as $partido) {
            $sinLocal = $partido->local === null && $partido->origenLocal === null;
            $sinVisitante = $partido->visitante === null && $partido->origenVisitante === null;

            if ($sinLocal && $sinVisitante) {
                $cuadro = $cuadro->conPartidos(array_map(fn (Partido $p): Partido => $p->id === $partido->id ? $p->con(estado: EstadoPartido::Walkover) : $p, $cuadro->partidos));
                $cuadro = new Cuadro($cuadro->tamano, $cuadro->rondas, $cuadro->partidos, [...$cuadro->avisos, "El partido {$partido->id} de la primera ronda se queda sin nadie: el cuadro es más grande de lo necesario."]);
            } elseif ($sinLocal || $sinVisitante) {
                $ganador = $partido->local ?? $partido->visitante;
                if ($ganador !== null) {
                    $cuadro = $this->avance->avanzar($cuadro, $partido->con(ganador: $ganador, estado: EstadoPartido::Walkover));
                }
            }
        }

        return $cuadro;
    }

    public static function potenciaDeDos(int $n): int
    {
        $t = 2;
        while ($t < $n) {
            $t *= 2;
        }

        return $t;
    }

    /**
     * El orden de siembra estándar: en qué hueco va cada cabeza para que 1
     * y 2 estén en mitades opuestas, 3 y 4 en los cuartos que quedan…
     * Devuelve hueco (desde 0) → número de cabeza (desde 1).
     *
     * @return list<int>
     */
    public static function ordenDeSiembra(int $tamano): array
    {
        $orden = [1];

        while (count($orden) < $tamano) {
            $siguiente = [];
            $total = count($orden) * 2 + 1;
            foreach ($orden as $cabeza) {
                $siguiente[] = $cabeza;
                $siguiente[] = $total - $cabeza;
            }
            $orden = $siguiente;
        }

        return $orden;
    }

    /**
     * @param  list<Clasificado>  $clasificados  Ordenados por `orden`.
     * @param  array<int, Clasificado|null>  $porCabeza  Cabeza (desde 1) → clasificado o nulo (bye).
     * @return list<int|null>
     */
    private function huecosDesdeCabezas(int $tamano, array $porCabeza): array
    {
        return array_map(fn (int $cabeza): ?int => $porCabeza[$cabeza]?->participante, self::ordenDeSiembra($tamano));
    }

    /**
     * @param  list<Clasificado>  $clasificados
     * @return list<int|null>
     */
    private function huecosPorSorteo(array $clasificados, int $tamano, ConfiguracionDeEliminatoria $configuracion): array
    {
        $byes = $tamano - count($clasificados);
        $conBye = array_slice($clasificados, 0, $byes);
        $resto = array_slice($clasificados, $byes);

        mt_srand($configuracion->semilla ?? random_int(1, PHP_INT_MAX >> 1));
        for ($i = count($resto) - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            [$resto[$i], $resto[$j]] = [$resto[$j], $resto[$i]];
        }

        $porCabeza = [];
        foreach ([...$conBye, ...$resto] as $i => $clasificado) {
            $porCabeza[$i + 1] = $clasificado;
        }
        for ($cabeza = count($clasificados) + 1; $cabeza <= $tamano; $cabeza++) {
            $porCabeza[$cabeza] = null;
        }

        return $this->huecosDesdeCabezas($tamano, $porCabeza);
    }

    /**
     * @param  list<Clasificado>  $clasificados
     * @return list<int|null>
     */
    private function huecosManuales(array $clasificados, int $tamano, ConfiguracionDeEliminatoria $configuracion): array
    {
        $cruces = $configuracion->crucesManuales ?? [];
        $ids = array_map(fn (Clasificado $c): int => $c->participante, $clasificados);

        if (count($cruces) !== $tamano / 2) {
            throw new ConfiguracionInvalida(['Hacen falta '.($tamano / 2).' cruces para un cuadro de '.$tamano.'.']);
        }

        $huecos = [];
        $vistos = [];
        foreach ($cruces as [$a, $b]) {
            foreach ([$a, $b] as $id) {
                if ($id === null) {
                    continue;
                }
                if (! in_array($id, $ids, true)) {
                    throw new ConfiguracionInvalida(["El participante {$id} no está entre los clasificados."]);
                }
                if (in_array($id, $vistos, true)) {
                    throw new ParticipanteDuplicado($id);
                }
                $vistos[] = $id;
            }
            $huecos[] = $a;
            $huecos[] = $b;
        }

        $faltan = array_diff($ids, $vistos);
        if ($faltan !== []) {
            throw new ConfiguracionInvalida(['Faltan en los cruces los participantes '.implode(', ', $faltan).'.']);
        }

        return $huecos;
    }

    /**
     * Siembra por orden global (1º de un grupo contra 2º de otro, byes a
     * los mejores) y, si se pide, permuta dentro de cada escalón de
     * clasificados (todos los segundos entre sí, etc.) hasta que ningún
     * cruce de primera ronda repita grupo.
     *
     * @param  list<Clasificado>  $clasificados
     * @param  list<string>  $avisos
     * @return list<int|null>
     */
    private function huecosEntreGrupos(array $clasificados, int $tamano, ConfiguracionDeEliminatoria $configuracion, array &$avisos): array
    {
        $porCabeza = $this->cabezasPorOrden($clasificados, $tamano);

        if (! $configuracion->evitarReencuentros || $this->reencuentros($porCabeza, $tamano) === 0) {
            return $this->huecosDesdeCabezas($tamano, $porCabeza);
        }

        $escalones = [];
        foreach ($clasificados as $clasificado) {
            $escalones[$clasificado->posicionEnGrupo][] = $clasificado;
        }
        ksort($escalones);
        $permutables = array_slice(array_values($escalones), 1);

        $intentos = 0;
        $mejor = $porCabeza;
        $menos = $this->reencuentros($porCabeza, $tamano);

        $this->probar($permutables, 0, $escalones, $tamano, $intentos, $mejor, $menos);

        if ($menos > 0) {
            $avisos[] = 'No se ha podido evitar '.$menos.' reencuentro(s) de la fase de grupos en la primera ronda.';
        }

        return $this->huecosDesdeCabezas($tamano, $mejor);
    }

    /**
     * @param  list<Clasificado>  $clasificados
     * @return array<int, Clasificado|null>
     */
    private function cabezasPorOrden(array $clasificados, int $tamano): array
    {
        $porCabeza = [];
        foreach (array_values($clasificados) as $i => $clasificado) {
            $porCabeza[$i + 1] = $clasificado;
        }
        for ($cabeza = count($clasificados) + 1; $cabeza <= $tamano; $cabeza++) {
            $porCabeza[$cabeza] = null;
        }

        return $porCabeza;
    }

    /**
     * @param  array<int, Clasificado|null>  $porCabeza
     */
    private function reencuentros(array $porCabeza, int $tamano): int
    {
        $huecos = array_map(fn (int $cabeza): ?Clasificado => $porCabeza[$cabeza], self::ordenDeSiembra($tamano));
        $repetidos = 0;

        for ($i = 0; $i < $tamano; $i += 2) {
            $a = $huecos[$i];
            $b = $huecos[$i + 1];
            if ($a !== null && $b !== null && $a->grupo !== null && $a->grupo === $b->grupo) {
                $repetidos++;
            }
        }

        return $repetidos;
    }

    /**
     * Recorre permutaciones de cada escalón (con tope de intentos) y se
     * queda con la que menos reencuentros deja.
     *
     * @param  list<list<Clasificado>>  $permutables
     * @param  array<int, list<Clasificado>>  $escalones
     * @param  array<int, Clasificado|null>  $mejor
     */
    private function probar(array $permutables, int $indice, array $escalones, int $tamano, int &$intentos, array &$mejor, int &$menos): void
    {
        if ($menos === 0 || $intentos >= self::INTENTOS_CONTRA_REENCUENTROS) {
            return;
        }

        if ($indice >= count($permutables)) {
            $intentos++;
            $lista = array_merge(...array_values($escalones));
            $porCabeza = $this->cabezasPorOrden($lista, $tamano);
            $repetidos = $this->reencuentros($porCabeza, $tamano);

            if ($repetidos < $menos) {
                $menos = $repetidos;
                $mejor = $porCabeza;
            }

            return;
        }

        $posicion = $permutables[$indice][0]->posicionEnGrupo;

        foreach ($this->permutaciones($permutables[$indice]) as $permutacion) {
            $escalones[$posicion] = $permutacion;
            $this->probar($permutables, $indice + 1, $escalones, $tamano, $intentos, $mejor, $menos);

            if ($menos === 0 || $intentos >= self::INTENTOS_CONTRA_REENCUENTROS) {
                return;
            }
        }
    }

    /**
     * @param  list<Clasificado>  $elementos
     * @return iterable<list<Clasificado>>
     */
    private function permutaciones(array $elementos): iterable
    {
        if (count($elementos) <= 1) {
            yield $elementos;

            return;
        }

        foreach ($elementos as $i => $elemento) {
            $resto = $elementos;
            unset($resto[$i]);
            foreach ($this->permutaciones(array_values($resto)) as $permutacion) {
                yield [$elemento, ...$permutacion];
            }
        }
    }
}
