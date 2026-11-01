<?php

namespace App\Policies;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Base de las políticas del panel: el propietario lo puede todo sobre su
 * organización; un administrador de torneo gestiona la competición (lo
 * que diga cada política hija); un árbitro no entra en el panel.
 */
abstract class PoliticaDelPanel
{
    /** Quién puede leer y escribir: por defecto, propietario y administradores. */
    protected function gestiona(User $usuario): bool
    {
        return in_array($usuario->rol, [Rol::Propietario, Rol::Administrador], true);
    }

    public function viewAny(User $usuario): bool
    {
        return $this->gestiona($usuario);
    }

    public function view(User $usuario, Model $modelo): bool
    {
        return $this->gestiona($usuario);
    }

    public function create(User $usuario): bool
    {
        return $this->gestiona($usuario);
    }

    public function update(User $usuario, Model $modelo): bool
    {
        return $this->gestiona($usuario);
    }

    public function delete(User $usuario, Model $modelo): bool
    {
        return $this->gestiona($usuario);
    }

    public function restore(User $usuario, Model $modelo): bool
    {
        return $usuario->esPropietario();
    }

    public function forceDelete(User $usuario, Model $modelo): bool
    {
        return $usuario->esPropietario();
    }
}
