<?php

namespace App\Enums;

/**
 * Pendiente (sin sortear), preparada (grupos o cuadro hechos, sin
 * resultados), en juego (algún resultado) y cerrada (clasificados
 * decididos y, si hay, la siguiente fase rellenada).
 */
enum EstadoFase: string
{
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
}
