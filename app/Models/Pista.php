<?php

namespace App\Models;

use App\Models\Concerns\VaALaPapelera;
use App\Tenancy\EnLaBaseDelOrganizador;
use Database\Factories\PistaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una pista del organizador. Cada torneo elige cuáles usa y con qué
 * disponibilidad (`torneo_pistas`).
 *
 * @property int $id
 * @property int|null $sede_id
 * @property string $nombre
 * @property int $orden
 * @property bool $activa
 */
#[Fillable(['sede_id', 'nombre', 'orden', 'activa'])]
class Pista extends Model
{
    /** @use HasFactory<PistaFactory> */
    use EnLaBaseDelOrganizador, HasFactory, VaALaPapelera;

    protected function casts(): array
    {
        return ['activa' => 'boolean'];
    }

    /** @return BelongsTo<Sede, $this> */
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }
}
