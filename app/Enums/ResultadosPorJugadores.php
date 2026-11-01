<?php

namespace App\Enums;

use App\Enums\Concerns\ConEtiqueta;

/**
 * Si los jugadores pueden registrar resultados y si el rival tiene que
 * confirmarlos antes de contar.
 */
enum ResultadosPorJugadores: string
{
    use ConEtiqueta;

    case Desactivado = 'desactivado';
    case ConValidacion = 'con_validacion';
    case SinValidacion = 'sin_validacion';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Desactivado => 'Solo la organización',
            self::ConValidacion => 'Los jugadores, con validación del rival',
            self::SinValidacion => 'Los jugadores, sin validación',
        };
    }
}
