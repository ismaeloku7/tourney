<?php

namespace App\Enums;

use App\Enums\Concerns\ConEtiqueta;

/** Un participante retirado conserva sus resultados; no se le programan más partidos. */
enum EstadoParticipante: string
{
    use ConEtiqueta;

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
