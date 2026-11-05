<?php

namespace App\Competicion;

use App\Competicion\Datos\Clasificado;
use App\Competicion\Datos\ConfiguracionDeEliminatoria;
use App\Competicion\Datos\Origen;
use App\Competicion\Errores\ErrorDeCompeticion;
use App\Enums\ReglaDeCruces;
use App\Enums\TipoDeOrigen;

/**
 * El esquema de un cuadro antes de jugarse, para verlo al configurarlo:
 * cada ronda (octavos, cuartos, semis, final) con sus cruces y, en cada
 * hueco, quién llega («1º A», «Ganador C1», «Bye»). Se arma con el mismo
 * constructor que el cuadro de verdad, con las posiciones de la fase de
 * grupos («1A», «2B», «M1») o los cabezas de serie («CS1»…) en su lugar.
 *
 * Entre los primeros (y entre los segundos…) el orden real sale de sus
 * resultados: aquí se toman por orden de grupo, así que es orientativo.
 * Por sorteo, los huecos de la primera ronda salen como «Sorteo».
 */
final readonly class EsquemaDeCuadro
{
    public function __construct(private ConstructorDeCuadro $constructor = new ConstructorDeCuadro) {}

    /**
     * @param  list<string>  $codigos  Quién pasa, en orden: «1A», «1B»… «2A»… «M1», o «CS1»… sin fase de grupos.
     * @return array{rondas: list<array{nombre: string, partidos: list<array{codigo: string, local: string, visitante: string, localBye: bool, visitanteBye: bool}>}>, tercero: array{codigo: string, local: string, visitante: string}|null, error: string|null}
     */
    public function armar(array $codigos, ConfiguracionDeEliminatoria $configuracion, ?int $mejoresDePosicion = null): array
    {
        $vacio = ['rondas' => [], 'tercero' => null, 'error' => null];

        if (count($codigos) < 2) {
            return [...$vacio, 'error' => 'Hacen falta al menos 2 parejas que pasen para ver el cuadro.'];
        }

        $clasificados = [];
        foreach (array_values($codigos) as $i => $codigo) {
            $clasificados[] = $this->clasificado($codigo, $i + 1);
        }

        try {
            $cuadro = $this->constructor->construir($clasificados, $configuracion->reglaDeCruces === ReglaDeCruces::Sorteo
                // Por sorteo la forma es la misma; los nombres se tapan después.
                ? new ConfiguracionDeEliminatoria($configuracion->tamanoDeCuadro, ReglaDeCruces::EntreGrupos, false, $configuracion->tercerYCuartoPuesto)
                : $configuracion);
        } catch (ErrorDeCompeticion $e) {
            return [...$vacio, 'error' => $e->getMessage()];
        }

        $sorteo = $configuracion->reglaDeCruces === ReglaDeCruces::Sorteo;
        $codigoDe = [];
        foreach (array_values($codigos) as $i => $codigo) {
            $codigoDe[$i + 1] = $codigo;
        }

        // Cada partido con su código de ronda: O1…O8, C1…C4, S1, S2, F.
        $nombreDe = [];
        for ($ronda = 1; $ronda <= $cuadro->rondas; $ronda++) {
            $deRonda = $cuadro->partidosDeRonda($ronda);
            foreach ($deRonda as $partido) {
                $nombreDe[(int) $partido->id] = self::prefijo(count($deRonda)).($cuadro->rondas === $ronda ? '' : $partido->posicion);
            }
        }

        $hueco = function (?int $participante, ?Origen $origen, int $ronda) use ($codigoDe, $nombreDe, $sorteo, $mejoresDePosicion): array {
            if ($participante !== null) {
                return [$sorteo ? 'Sorteo' : self::etiqueta($codigoDe[$participante] ?? '?', $mejoresDePosicion), false];
            }
            if ($origen !== null) {
                $de = $nombreDe[$origen->referencia] ?? '?';

                return [($origen->tipo === TipoDeOrigen::PerdedorDe ? 'Perdedor ' : 'Ganador ').$de, false];
            }

            return [$ronda === 1 ? 'Bye' : '—', true];
        };

        $rondas = [];
        $tercero = null;
        for ($ronda = 1; $ronda <= $cuadro->rondas; $ronda++) {
            $partidos = [];
            foreach ($cuadro->partidosDeRonda($ronda) as $partido) {
                // Quien ya se sabe (también el que pasa por un bye) sale con su nombre; si no, de dónde viene.
                [$local, $localBye] = $hueco($partido->local, $partido->local === null ? $partido->origenLocal : null, $ronda);
                [$visitante, $visitanteBye] = $hueco($partido->visitante, $partido->visitante === null ? $partido->origenVisitante : null, $ronda);

                $partidos[] = ['codigo' => $nombreDe[(int) $partido->id], 'local' => $local, 'visitante' => $visitante, 'localBye' => $localBye, 'visitanteBye' => $visitanteBye];
            }
            $rondas[] = ['nombre' => self::nombreDeRonda(count($partidos)), 'partidos' => $partidos];
        }

        $partido = $cuadro->tercerPuesto();
        if ($partido !== null) {
            $tercero = ['codigo' => '3º', 'local' => $hueco(null, $partido->origenLocal, 2)[0], 'visitante' => $hueco(null, $partido->origenVisitante, 2)[0]];
        }

        return ['rondas' => $rondas, 'tercero' => $tercero, 'error' => null];
    }

    /** «Final», «Semifinales», «Cuartos», «Octavos»… por cuántos partidos tiene la ronda. */
    public static function nombreDeRonda(int $partidos): string
    {
        return match (true) {
            $partidos <= 1 => 'Final',
            $partidos === 2 => 'Semifinales',
            $partidos === 4 => 'Cuartos',
            $partidos === 8 => 'Octavos',
            $partidos === 16 => 'Dieciseisavos',
            $partidos === 32 => 'Treintaidosavos',
            default => 'Ronda de '.(2 * $partidos),
        };
    }

    /** «1A» → «1º A»; «M2» → «Mejor 3º nº 2»; «CS3» → «Cabeza 3». */
    public static function etiqueta(string $codigo, ?int $mejoresDePosicion = null): string
    {
        if (str_starts_with($codigo, 'CS')) {
            return 'Cabeza '.substr($codigo, 2);
        }
        if (str_starts_with($codigo, 'M')) {
            return 'Mejor '.($mejoresDePosicion !== null ? $mejoresDePosicion.'º ' : '').'nº '.substr($codigo, 1);
        }
        if (preg_match('/^(\d+)([A-Z]+)$/', $codigo, $partes) === 1) {
            return $partes[1].'º '.$partes[2];
        }

        return $codigo;
    }

    private static function prefijo(int $partidos): string
    {
        return match (true) {
            $partidos <= 1 => 'F',
            $partidos === 2 => 'S',
            $partidos === 4 => 'C',
            $partidos === 8 => 'O',
            $partidos === 16 => 'D',
            default => 'R',
        };
    }

    /** Un clasificado de mentira con lo que dice su código. */
    private function clasificado(string $codigo, int $orden): Clasificado
    {
        if (preg_match('/^(\d+)([A-Z]+)$/', $codigo, $partes) === 1) {
            $grupo = 0;
            foreach (str_split($partes[2]) as $letra) {
                $grupo = $grupo * 26 + (ord($letra) - 64);
            }

            return new Clasificado($orden, $grupo, (int) $partes[1], $partes[1].'º '.$partes[2], $orden);
        }

        if (str_starts_with($codigo, 'M')) {
            return new Clasificado($orden, null, 99, 'mejor '.$codigo, $orden);
        }

        return new Clasificado($orden, null, 1, $codigo, $orden);
    }
}
