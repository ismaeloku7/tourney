<?php

namespace App\Actions\Jugadores;

use App\Models\EnlaceMagico;
use App\Models\Jugador;

/** Gasta un enlace de acceso: el jugador si vale, nulo si no existe, caducó o ya se usó. */
final class UsarEnlaceDeAcceso
{
    public function handle(string $token): ?Jugador
    {
        $enlace = EnlaceMagico::query()->where('token', hash('sha256', $token))->with('jugador')->first();

        if ($enlace === null || $enlace->usado_en !== null || $enlace->expira_en->isPast() || $enlace->jugador === null || $enlace->jugador->anonimizado_en !== null) {
            return null;
        }

        $enlace->forceFill(['usado_en' => now()])->save();

        return $enlace->jugador;
    }
}
