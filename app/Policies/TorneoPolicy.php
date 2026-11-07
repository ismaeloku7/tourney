<?php

namespace App\Policies;

use App\Enums\Rol;
use App\Models\Torneo;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * El propietario ve y toca todos los torneos. Un administrador de torneo,
 * solo los que tiene asignados (`torneo_administradores`), y no crea ni
 * borra torneos. Un árbitro (o mesa) ve los que arbitra (`torneo_arbitros`)
 * y en ellos lleva los partidos: resultados y moverlos de hora o pista;
 * el torneo en sí no lo cambia.
 */
class TorneoPolicy extends PoliticaDelPanel
{
    public function viewAny(User $usuario): bool
    {
        return $this->gestiona($usuario) || $usuario->esArbitro();
    }

    public function view(User $usuario, Model $modelo): bool
    {
        return $this->update($usuario, $modelo) || ($modelo instanceof Torneo && $this->arbitra($usuario, $modelo));
    }

    public function create(User $usuario): bool
    {
        return $usuario->esPropietario();
    }

    public function update(User $usuario, Model $modelo): bool
    {
        return $usuario->esPropietario() || ($modelo instanceof Torneo && $this->administra($usuario, $modelo));
    }

    /** Llevar sus partidos: poner resultados y moverlos de hora o pista. */
    public function gestionarPartidos(User $usuario, Torneo $torneo): bool
    {
        return $this->update($usuario, $torneo) || $this->arbitra($usuario, $torneo);
    }

    public function delete(User $usuario, Model $modelo): bool
    {
        return $usuario->esPropietario();
    }

    private function administra(User $usuario, Torneo $torneo): bool
    {
        return $usuario->rol === Rol::Administrador && $torneo->administradores()->whereKey($usuario->id)->exists();
    }

    private function arbitra(User $usuario, Torneo $torneo): bool
    {
        return $usuario->esArbitro() && in_array($torneo->id, $usuario->idsDeTorneosQueArbitra(), true);
    }
}
