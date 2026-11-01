<?php

namespace App\Enums;

use App\Enums\Concerns\ConEtiqueta;

enum EstadoPartido: string
{
    use ConEtiqueta;

    case Pendiente = 'pendiente';
    case Programado = 'programado';
    case EnJuego = 'en_juego';
    case PendienteValidacion = 'pendiente_validacion';
    case Jugado = 'jugado';
    case Walkover = 'walkover';
    case Anulado = 'anulado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Programado => 'Programado',
            self::EnJuego => 'En juego',
            self::PendienteValidacion => 'Pendiente de validación',
            self::Jugado => 'Jugado',
            self::Walkover => 'Walkover',
            self::Anulado => 'Anulado',
        };
    }

    /** Un partido cerrado cuenta para la clasificación y el cuadro. */
    public function cerrado(): bool
    {
        return $this === self::Jugado || $this === self::Walkover;
    }

    /** Un partido que aún se puede programar o jugar. */
    public function abierto(): bool
    {
        return $this === self::Pendiente || $this === self::Programado || $this === self::EnJuego || $this === self::PendienteValidacion;
    }
}
