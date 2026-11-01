<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Una estancia de un organizador en un plan. La fila abierta (`hasta`
 * nulo) es su plan actual, copiado en `organizadores.plan_id`.
 *
 * @property int $id
 * @property string $organizador_id
 * @property int $plan_id
 * @property CarbonInterface $desde
 * @property CarbonInterface|null $hasta
 * @property string|null $notas
 * @property int|null $cambiado_por
 */
#[Fillable(['organizador_id', 'plan_id', 'desde', 'hasta', 'notas', 'cambiado_por'])]
class SuscripcionPlan extends Model
{
    use CentralConnection;

    protected $table = 'suscripciones_plan';

    protected function casts(): array
    {
        return [
            'desde' => 'date',
            'hasta' => 'date',
        ];
    }

    /** @return BelongsTo<Organizador, $this> */
    public function organizador(): BelongsTo
    {
        return $this->belongsTo(Organizador::class);
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** @return BelongsTo<UsuarioPlataforma, $this> */
    public function cambiadoPor(): BelongsTo
    {
        return $this->belongsTo(UsuarioPlataforma::class, 'cambiado_por');
    }
}
