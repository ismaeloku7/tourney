<?php

namespace App\Models;

use App\Tenancy\EnLaBaseDelOrganizador;
use Database\Factories\TorneoPistaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una pista usada por un torneo, con los tramos en los que está
 * disponible: lista de `{fecha, desde, hasta}` (DTO DisponibilidadDePista).
 * De aquí se generan las franjas.
 *
 * @property int $id
 * @property int $torneo_id
 * @property int $pista_id
 * @property int $orden
 * @property list<array{fecha: string, desde: string, hasta: string}> $disponibilidad
 */
#[Fillable(['torneo_id', 'pista_id', 'orden', 'disponibilidad'])]
class TorneoPista extends Model
{
    /** @use HasFactory<TorneoPistaFactory> */
    use EnLaBaseDelOrganizador, HasFactory;

    protected $table = 'torneo_pistas';

    protected function casts(): array
    {
        return ['disponibilidad' => 'array'];
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
}
