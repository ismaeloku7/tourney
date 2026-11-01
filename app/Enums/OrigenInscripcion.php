<?php

namespace App\Enums;

enum OrigenInscripcion: string
{
    case Online = 'online';
    case Manual = 'manual';
    case Importacion = 'importacion';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Online => 'Inscripción online',
            self::Manual => 'Apuntada por la organización',
            self::Importacion => 'Importada',
        };
    }
}
