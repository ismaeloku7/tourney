<?php

namespace App\Enums;

enum ModoDeSorteo: string
{
    case Aleatorio = 'aleatorio';
    case CabezasDeSerie = 'cabezas_de_serie';
    case Manual = 'manual';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Aleatorio => 'Aleatorio',
            self::CabezasDeSerie => 'Con cabezas de serie',
            self::Manual => 'Manual',
        };
    }
}
