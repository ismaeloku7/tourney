<?php

namespace App\Competicion\Errores;

/** Un resultado que no cumple el formato del partido, con todos sus errores. */
class ResultadoInvalido extends ErrorDeCompeticion
{
    /** @param  list<string>  $errores */
    public function __construct(public readonly array $errores)
    {
        parent::__construct(implode(' ', $errores));
    }
}
