<?php

namespace App\Enums;

use App\Enums\Concerns\ConEtiqueta;

/**
 * Los roles del equipo de un organizador. El propietario lo puede todo
 * sobre su organización; un administrador de torneo gestiona los torneos
 * que se le asignan; un árbitro solo introduce resultados en sus pistas.
 * Los jugadores no son `User`: entran por el portal del jugador.
 */
enum Rol: string
{
    use ConEtiqueta;

    case Propietario = 'propietario';
    case Administrador = 'administrador';
    case Arbitro = 'arbitro';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Propietario => 'Propietario',
            self::Administrador => 'Administrador de torneo',
            self::Arbitro => 'Árbitro o mesa',
        };
    }
}
