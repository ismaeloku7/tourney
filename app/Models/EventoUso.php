<?php

namespace App\Models;

use App\Enums\TipoDeEventoDeUso;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Un hecho de uso de un organizador, para las métricas de la plataforma.
 * Se escribe desde la base del organizador con `anotar()`, que va a la
 * central aunque haya un organizador activo.
 *
 * @property int $id
 * @property string $organizador_id
 * @property TipoDeEventoDeUso $tipo
 * @property int $cantidad
 * @property string|null $referencia
 * @property CarbonInterface $ocurrido_en
 */
#[Fillable(['organizador_id', 'tipo', 'cantidad', 'referencia', 'ocurrido_en'])]
class EventoUso extends Model
{
    use CentralConnection;

    public $timestamps = false;

    protected $table = 'eventos_uso';

    protected function casts(): array
    {
        return [
            'tipo' => TipoDeEventoDeUso::class,
            'cantidad' => 'integer',
            'ocurrido_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<Organizador, $this> */
    public function organizador(): BelongsTo
    {
        return $this->belongsTo(Organizador::class);
    }

    public static function anotar(Organizador $organizador, TipoDeEventoDeUso $tipo, int $cantidad = 1, ?string $referencia = null): self
    {
        return self::query()->create([
            'organizador_id' => $organizador->id,
            'tipo' => $tipo,
            'cantidad' => $cantidad,
            'referencia' => $referencia,
            'ocurrido_en' => now(),
        ]);
    }
}
