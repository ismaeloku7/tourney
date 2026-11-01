<?php

namespace App\Competicion;

use App\Competicion\Datos\Cuadro;
use App\Competicion\Datos\Partido;
use App\Competicion\Errores\AvanceNoReversible;
use App\Enums\EstadoPartido;
use App\Enums\TipoDeOrigen;

/**
 * Lleva al ganador (y al perdedor, si hay tercer puesto o consolación) de
 * un partido cerrado a los huecos que lo esperan. Idempotente: cerrar dos
 * veces no cambia nada. Si el rival de un hueco es un bye, el partido
 * siguiente se cierra solo como walkover. Revertir vacía los huecos y
 * lanza si el partido siguiente ya se jugó.
 */
final readonly class AvanceDeCuadro
{
    public function avanzar(Cuadro $cuadro, Partido $cerrado): Cuadro
    {
        $partidos = $this->reemplazar($cuadro->partidos, $cerrado);
        $cierresEnCadena = [];

        foreach ($partidos as $i => $partido) {
            $nuevo = $this->rellenar($partido, $cerrado);

            if ($nuevo === $partido) {
                continue;
            }

            if (! $nuevo->cerrado() && $this->tieneByeEnfrente($nuevo)) {
                $ganador = $nuevo->local ?? $nuevo->visitante;
                assert($ganador !== null);
                $nuevo = $nuevo->con(ganador: $ganador, estado: EstadoPartido::Walkover, resultado: null);
                $cierresEnCadena[] = $nuevo;
            }

            $partidos[$i] = $nuevo;
        }

        $resultado = $cuadro->conPartidos($partidos);

        foreach ($cierresEnCadena as $siguiente) {
            $resultado = $this->avanzar($resultado, $siguiente);
        }

        return $resultado;
    }

    public function revertir(Cuadro $cuadro, Partido $anulado): Cuadro
    {
        $partidos = $cuadro->partidos;

        foreach ($partidos as $partido) {
            if (! $this->dependeDe($partido, $anulado->id)) {
                continue;
            }

            if ($partido->cerrado() && ! $this->esByeAutomatico($partido)) {
                throw new AvanceNoReversible((int) $partido->id);
            }
        }

        $resultado = $cuadro;

        foreach ($partidos as $partido) {
            if (! $this->dependeDe($partido, $anulado->id)) {
                continue;
            }

            // Un cierre automático por bye se deshace antes de vaciar el hueco.
            if ($this->esByeAutomatico($partido)) {
                $resultado = $this->revertir($resultado, $partido);
            }

            $vaciado = $resultado->partido((int) $partido->id) ?? $partido;

            if ($vaciado->origenLocal?->apuntaA((int) $anulado->id)) {
                $vaciado = $vaciado->con(local: null);
            }
            if ($vaciado->origenVisitante?->apuntaA((int) $anulado->id)) {
                $vaciado = $vaciado->con(visitante: null);
            }

            $resultado = $resultado->conPartidos($this->reemplazar($resultado->partidos, $vaciado->vacio()));
        }

        return $resultado->conPartidos($this->reemplazar($resultado->partidos, ($resultado->partido((int) $anulado->id) ?? $anulado)->vacio()));
    }

    private function rellenar(Partido $partido, Partido $cerrado): Partido
    {
        $id = (int) $cerrado->id;

        if ($partido->origenLocal?->apuntaA($id)) {
            $valor = $this->valorDe($partido->origenLocal->tipo, $cerrado);
            if ($valor !== null && $partido->local !== $valor) {
                $partido = $partido->con(local: $valor);
            }
        }

        if ($partido->origenVisitante?->apuntaA($id)) {
            $valor = $this->valorDe($partido->origenVisitante->tipo, $cerrado);
            if ($valor !== null && $partido->visitante !== $valor) {
                $partido = $partido->con(visitante: $valor);
            }
        }

        return $partido;
    }

    private function valorDe(TipoDeOrigen $tipo, Partido $cerrado): ?int
    {
        return $tipo === TipoDeOrigen::GanadorDe ? $cerrado->ganador : $cerrado->perdedor();
    }

    /** Un lado con participante y el otro sin participante ni origen: un bye. */
    private function tieneByeEnfrente(Partido $partido): bool
    {
        return ($partido->local !== null && $partido->visitante === null && $partido->origenVisitante === null)
            || ($partido->visitante !== null && $partido->local === null && $partido->origenLocal === null);
    }

    /** Un partido cerrado solo porque enfrente había un bye. */
    private function esByeAutomatico(Partido $partido): bool
    {
        return $partido->estado === EstadoPartido::Walkover && $partido->resultado === null && $partido->ganador !== null && $this->tieneByeEnfrente($partido);
    }

    private function dependeDe(Partido $partido, ?int $id): bool
    {
        return $id !== null && (($partido->origenLocal?->apuntaA($id) ?? false) || ($partido->origenVisitante?->apuntaA($id) ?? false));
    }

    /**
     * @param  list<Partido>  $partidos
     * @return list<Partido>
     */
    private function reemplazar(array $partidos, Partido $nuevo): array
    {
        $encontrado = false;

        foreach ($partidos as $i => $partido) {
            if ($partido->id === $nuevo->id) {
                $partidos[$i] = $nuevo;
                $encontrado = true;
            }
        }

        if (! $encontrado) {
            $partidos[] = $nuevo;
        }

        return array_values($partidos);
    }
}
