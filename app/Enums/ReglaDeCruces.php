<?php

namespace App\Enums;

enum ReglaDeCruces: string
{
    case EntreGrupos = 'entre_grupos';
    case Sorteo = 'sorteo';
    case Manual = 'manual';

    public function etiqueta(): string
    {
        return match ($this) {
            self::EntreGrupos => 'Cruces entre grupos (1º del A contra 2º del B)',
            self::Sorteo => 'Sorteo puro',
            self::Manual => 'Cruces manuales',
        };
    }
}
