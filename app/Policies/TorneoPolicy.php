<?php

namespace App\Policies;

use App\Enums\Rol;
use App\Models\Torneo;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * El propietario ve y toca todos los torneos. Un administrador de torneo,
 * solo los que tiene asignados (`torneo_administradores`), y no crea ni
 * borra torneos.
 */
class TorneoPolicy extends PoliticaDelPanel
{
    public function view(User $usuario, Model $modelo): bool
    {
        return $usuario->esPropietario() || ($modelo instanceof Torneo && $this->administra($usuario, $modelo));
    }

    public function create(User $usuario): bool
    {
        return $usuario->esPropietario();
    }

    public function update(User $usuario, Model $modelo): bool
    {
        return $this->view($usuario, $modelo);
    }

    public function delete(User $usuario, Model $modelo): bool
    {
        return $usuario->esPropietario();
    }

    private function administra(User $usuario, Torneo $torneo): bool
    {
        return $usuario->rol === Rol::Administrador && $torneo->administradores()->whereKey($usuario->id)->exists();
    }
}
