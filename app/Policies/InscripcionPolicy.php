<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * @see PoliticaDelPanel
 *
 * Un árbitro (o mesa) también ve las parejas de sus torneos, sin cambiarlas.
 */
class InscripcionPolicy extends PoliticaDelPanel
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
