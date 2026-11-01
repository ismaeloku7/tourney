<?php

namespace App\Models;

use App\Tenancy\EnLaBaseDelOrganizador;
use Database\Factories\DisponibilidadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un intervalo en el que un jugador NO puede jugar en un torneo. El
 * planificador no le pone partidos dentro.
 *
 * @property int $id
 * @property int $jugador_id
 * @property int $torneo_id
 * @property Carbon $desde
 * @property Carbon $hasta
 */
#[Fillable(['jugador_id', 'torneo_id', 'desde', 'hasta'])]
class Disponibilidad extends Model
{
    /** @use HasFactory<DisponibilidadFactory> */
    use EnLaBaseDelOrganizador, HasFactory;

    protected $table = 'disponibilidades';

    protected function casts(): array
    {
        return [
            'desde' => 'datetime',
            'hasta' => 'datetime',
        ];
    }

    /** @return BelongsTo<Jugador, $this> */
    public function jugador(): BelongsTo
    {
        return $this->belongsTo(Jugador::class);
    }

    /** @return BelongsTo<Torneo, $this> */
    public function torneo(): BelongsTo
    {
        return $this->belongsTo(Torneo::class);
    }
}
