<?php

namespace App\Publico;

use App\Models\Jugador;
use App\Models\Participante;
use App\Models\Torneo;

/**
 * Cómo se llama una pareja en público: por los apellidos de sus jugadores
 * o, si el torneo pide privacidad, por sus alias. Nunca correo ni teléfono.
 */
final class NombrePublico
{
    public static function participante(?Participante $participante, Torneo $torneo): ?string
    {
        if ($participante === null) {
            return null;
        }

        if (! $torneo->privacidad_alias) {
            return $participante->nombre;
        }

        $jugadores = $participante->relationLoaded('jugadores') ? $participante->jugadores : $participante->jugadores()->get();

        return $jugadores->map(fn (Jugador $j): string => $j->nombrePublico(true))->implode(' / ');
    }
}
