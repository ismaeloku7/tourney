<?php

namespace App\Models;

use App\Tenancy\EnLaBaseDelOrganizador;
use Database\Factories\FranjaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Un hueco de una pista en un torneo. Aloja como máximo un partido.
 *
 * @property int $id
 * @property int $torneo_id
 * @property int $pista_id
 * @property Carbon $inicio
 * @property Carbon $fin
 * @property bool $bloqueada
 */
#[Fillable(['torneo_id', 'pista_id', 'inicio', 'fin', 'bloqueada'])]
class Franja extends Model
{
    /** @use HasFactory<FranjaFactory> */
    use EnLaBaseDelOrganizador, HasFactory;

    protected function casts(): array
    {
        return [
            'inicio' => 'datetime',
            'fin' => 'datetime',
            'bloqueada' => 'boolean',
        ];
    }

    /** @return BelongsTo<Torneo, $this> */
    public function torneo(): BelongsTo
    {
        return $this->belongsTo(Torneo::class);
    }

    /** @return BelongsTo<Pista, $this> */
    public function pista(): BelongsTo
    {
        return $this->belongsTo(Pista::class);
    }

    /** @return HasOne<Partido, $this> */
    public function partido(): HasOne
    {
        return $this->hasOne(Partido::class);
    }
}
