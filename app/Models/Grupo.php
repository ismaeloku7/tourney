<?php

namespace App\Models;

use App\Competicion\Datos\Grupo as GrupoDto;
use App\Tenancy\EnLaBaseDelOrganizador;
use Database\Factories\GrupoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un grupo de una fase («A», «B»…) con sus participantes en orden de
 * sorteo.
 *
 * @property int $id
 * @property int $fase_id
 * @property string $nombre
 * @property int $orden
 */
#[Fillable(['fase_id', 'nombre', 'orden'])]
class Grupo extends Model
{
    /** @use HasFactory<GrupoFactory> */
    use EnLaBaseDelOrganizador, HasFactory;

    /** @return BelongsTo<Fase, $this> */
    public function fase(): BelongsTo
    {
        return $this->belongsTo(Fase::class);
    }

    /** @return BelongsToMany<Participante, $this> */
    public function participantes(): BelongsToMany
    {
        return $this->belongsToMany(Participante::class, 'grupo_participantes')->withPivot('posicion_sorteo')->orderByPivot('posicion_sorteo');
    }

    /** @return HasMany<Partido, $this> */
    public function partidos(): HasMany
    {
        return $this->hasMany(Partido::class)->orderBy('jornada');
    }

    /** @return HasMany<Clasificacion, $this> */
    public function clasificaciones(): HasMany
    {
        return $this->hasMany(Clasificacion::class)->orderBy('posicion');
    }

    /** La letra del grupo a partir de su orden: 1 → A, 27 → AA. */
    public static function nombrePorOrden(int $orden): string
    {
        return GrupoDto::nombrePorOrden($orden);
    }

    /** El grupo tal y como lo ve el motor, con sus participantes en orden de sorteo. */
    public function aDto(): GrupoDto
    {
        return new GrupoDto($this->nombre, $this->orden, array_values($this->participantes->pluck('id')->map(fn (mixed $id): int => (int) $id)->all()), $this->id);
    }
}
