<?php

namespace App\Enums;

use App\Enums\Concerns\ConEtiqueta;

/**
 * Pendiente (sin sortear), preparada (grupos o cuadro hechos, sin
 * resultados), en juego (algún resultado) y cerrada (clasificados
 * decididos y, si hay, la siguiente fase rellenada).
 */
enum EstadoFase: string
{
    use ConEtiqueta;

    case Pendiente = 'pendiente';
    case Preparada = 'preparada';
    case EnJuego = 'en_juego';
    case Cerrada = 'cerrada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente de sorteo',
            self::Preparada => 'Preparada',
            self::EnJuego => 'En juego',
            self::Cerrada => 'Cerrada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pendiente => 'gray',
            self::Preparada => 'info',
            self::EnJuego => 'warning',
            self::Cerrada => 'success',
        };
    }
}
