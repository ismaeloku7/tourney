<?php

namespace App\Competicion;

use App\Competicion\Datos\LineaDeClasificacion;

/**
 * Las cuentas de un participante mientras se recorren los partidos; al
 * acabar se convierte en una línea inmutable.
 */
final class AcumuladoDeLinea
{
    public int $puntos = 0;

    public int $pj = 0;

    public int $pg = 0;

    public int $pp = 0;

    public int $setsFavor = 0;

    public int $setsContra = 0;

    public int $juegosFavor = 0;

    public int $juegosContra = 0;

    public function __construct(public readonly int $participante) {}

    public function linea(): LineaDeClasificacion
    {
        return new LineaDeClasificacion($this->participante, 0, $this->puntos, $this->pj, $this->pg, $this->pp, $this->setsFavor, $this->setsContra, $this->juegosFavor, $this->juegosContra);
    }
}
