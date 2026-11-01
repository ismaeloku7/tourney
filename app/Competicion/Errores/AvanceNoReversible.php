<?php

namespace App\Competicion\Errores;

/** No se puede anular un resultado cuyo siguiente partido ya se ha jugado. */
class AvanceNoReversible extends ErrorDeCompeticion
{
    public function __construct(int $partidoSiguiente)
    {
        parent::__construct("El partido {$partidoSiguiente} ya tiene resultado: anule primero ese.");
    }
}
