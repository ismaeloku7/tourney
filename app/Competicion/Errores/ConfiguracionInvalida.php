<?php

namespace App\Competicion\Errores;

/** Una configuración (de fase, de formato, de disponibilidad) que no vale, con todos sus errores. */
class ConfiguracionInvalida extends ErrorDeCompeticion
{
    /** @param  list<string>  $errores */
    public function __construct(public readonly array $errores)
    {
        parent::__construct(implode(' ', $errores));
    }
}
