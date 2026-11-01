<?php

namespace App\Policies;

use App\Models\User;

/** Lo que es de la organización, no de un torneo: solo el propietario. */
abstract class SoloPropietario extends PoliticaDelPanel
{
    protected function gestiona(User $usuario): bool
    {
        return $usuario->esPropietario();
    }
}
