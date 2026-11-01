<?php

namespace App\Publico;

use App\Enums\EstadoTorneo;
use App\Enums\Rol;
use App\Models\Torneo;
use App\Models\TorneoArbitro;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Qué torneos y qué pistas lleva cada uno en la mesa: el propietario,
 * todos; un administrador, los suyos; un árbitro, los torneos y las pistas
 * que tiene asignados (sin pista, todas las del torneo). Solo torneos en
 * juego.
 */
final class PermisosDeMesa
{
    /** @return Collection<int, Torneo> */
    public static function torneos(User $usuario): Collection
    {
        $consulta = Torneo::query()->where('estado', EstadoTorneo::EnJuego->value)->orderBy('fecha_inicio');

        return match ($usuario->rol) {
            Rol::Propietario => $consulta->get(),
            Rol::Administrador => $consulta->whereHas('administradores', fn ($q) => $q->whereKey($usuario->id))->get(),
            Rol::Arbitro => $consulta->whereIn('id', TorneoArbitro::query()->where('user_id', $usuario->id)->select('torneo_id'))->get(),
        };
    }

    /**
     * Las pistas del torneo que lleva: nulo es todas.
     *
     * @return list<int>|null
     */
    public static function pistas(User $usuario, Torneo $torneo): ?array
    {
        if ($usuario->rol !== Rol::Arbitro) {
            return null;
        }

        $filas = TorneoArbitro::query()->where('user_id', $usuario->id)->where('torneo_id', $torneo->id)->get();

        if ($filas->contains(fn (TorneoArbitro $f): bool => $f->pista_id === null)) {
            return null;
        }

        return array_values($filas->pluck('pista_id')->map(fn (mixed $id): int => (int) $id)->all());
    }

    public static function puede(User $usuario, Torneo $torneo, ?int $pista): bool
    {
        if (! $usuario->activo || ! self::torneos($usuario)->contains('id', $torneo->id)) {
            return false;
        }

        $pistas = self::pistas($usuario, $torneo);

        return $pistas === null || ($pista !== null && in_array($pista, $pistas, true));
    }
}
