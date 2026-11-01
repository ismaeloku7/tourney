<?php

namespace App\Models;

use App\Models\Concerns\VaALaPapelera;
use App\Tenancy\EnLaBaseDelOrganizador;
use Database\Factories\SedeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una instalación del organizador con sus pistas.
 *
 * @property int $id
 * @property string $nombre
 * @property string|null $direccion
 * @property string|null $mapa_url
 * @property int $orden
 */
#[Fillable(['nombre', 'direccion', 'mapa_url', 'orden'])]
class Sede extends Model
{
    /** @use HasFactory<SedeFactory> */
    use EnLaBaseDelOrganizador, HasFactory, VaALaPapelera;

    /** @return HasMany<Pista, $this> */
    public function pistas(): HasMany
    {
        return $this->hasMany(Pista::class)->orderBy('orden');
    }
}
