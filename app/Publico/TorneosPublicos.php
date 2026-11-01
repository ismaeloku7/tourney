<?php

namespace App\Publico;

use App\Enums\EstadoTorneo;
use App\Models\Torneo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/** Los torneos que se ven en la web del organizador: publicados y no archivados. */
final class TorneosPublicos
{
    /** @return Builder<Torneo> */
    public static function consulta(): Builder
    {
        return Torneo::query()->whereNotIn('estado', [EstadoTorneo::Borrador->value, EstadoTorneo::Archivado->value])->with('sede');
    }

    public static function esPublico(Torneo $torneo): bool
    {
        return $torneo->estado->publicado() && $torneo->estado !== EstadoTorneo::Archivado;
    }

    /** @return Collection<int, Torneo> */
    public static function abiertos(): Collection
    {
        return self::consulta()->where('estado', EstadoTorneo::InscripcionAbierta->value)->orderBy('fecha_inicio')->get();
    }

    /** @return Collection<int, Torneo> */
    public static function enJuego(): Collection
    {
        return self::consulta()->where('estado', EstadoTorneo::EnJuego->value)->orderBy('fecha_inicio')->get();
    }

    /**
     * Publicados, con la inscripción cerrada o en sorteo: ya vienen.
     *
     * @return Collection<int, Torneo>
     */
    public static function proximos(): Collection
    {
        return self::consulta()->whereIn('estado', [EstadoTorneo::InscripcionCerrada->value, EstadoTorneo::Sorteo->value])->orderBy('fecha_inicio')->get();
    }

    /** @return Collection<int, Torneo> */
    public static function recientes(int $cuantos = 6): Collection
    {
        return self::consulta()->where('estado', EstadoTorneo::Finalizado->value)->orderByDesc('fecha_fin')->limit($cuantos)->get();
    }
}
