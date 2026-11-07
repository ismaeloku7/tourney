<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * @see PoliticaDelPanel
 *
 * Un árbitro (o mesa) también ve los jugadores de sus torneos, sin cambiarlos.
 */
class JugadorPolicy extends PoliticaDelPanel
{
    public function viewAny(User $usuario): bool
    {
        return $this->gestiona($usuario) || $usuario->esArbitro();
    }

    public function view(User $usuario, Model $modelo): bool
    {
        return $this->viewAny($usuario);
    }
}
