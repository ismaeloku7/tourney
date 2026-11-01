<?php

namespace App\Enums;

enum OrigenDeConsolacion: string
{
    case Ninguna = 'ninguna';
    case NoClasificados = 'no_clasificados';
    case PerdedoresPrimeraRonda = 'perdedores_primera_ronda';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Ninguna => 'Sin consolación',
            self::NoClasificados => 'Las parejas que no se clasifican',
            self::PerdedoresPrimeraRonda => 'Los perdedores de la primera ronda',
        };
    }
}
