<?php

namespace App\Enums;

/**
 * Quién compite: una persona, una pareja o un equipo. La v1 solo usa
 * parejas; el modelo queda preparado para las otras dos.
 */
enum Modalidad: string
{
    case Individual = 'individual';
    case Parejas = 'parejas';
    case Equipos = 'equipos';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Individual => 'Individual',
            self::Parejas => 'Parejas',
            self::Equipos => 'Equipos',
        };
    }

    /** Cuántos jugadores forman un participante; nulo, los que sean. */
    public function jugadoresPorParticipante(): ?int
    {
        return match ($this) {
            self::Individual => 1,
            self::Parejas => 2,
            self::Equipos => null,
        };
    }
}
