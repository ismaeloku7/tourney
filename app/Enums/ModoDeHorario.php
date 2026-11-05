<?php

namespace App\Enums;

use App\Enums\Concerns\ConEtiqueta;

/** Cómo se juegan los partidos: a la hora fijada, o uno detrás de otro por pista con hora estimada. */
enum ModoDeHorario: string
{
    use ConEtiqueta;

    case Fijo = 'fijo';
    case Encadenado = 'encadenado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Fijo => 'A hora fija',
            self::Encadenado => 'Encadenados: uno detrás de otro por pista',
        };
    }
}
