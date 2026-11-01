<?php

namespace App\Enums;

/** Un participante retirado conserva sus resultados; no se le programan más partidos. */
enum EstadoParticipante: string
{
    case Activo = 'activo';
    case Retirado = 'retirado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Activo => 'Activo',
            self::Retirado => 'Retirado',
        };
    }
}
