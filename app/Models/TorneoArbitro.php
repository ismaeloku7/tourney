<?php

namespace App\Models;

use App\Tenancy\EnLaBaseDelOrganizador;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un árbitro o mesa asignado a un torneo, a una pista concreta o a todas
 * (`pista_id` nulo).
 *
 * @property int $id
 * @property int $torneo_id
 * @property int $user_id
 * @property int|null $pista_id
 */
#[Fillable(['torneo_id', 'user_id', 'pista_id'])]
class TorneoArbitro extends Model
{
    use EnLaBaseDelOrganizador;

    protected $table = 'torneo_arbitros';

    /** @return BelongsTo<Torneo, $this> */
    public function torneo(): BelongsTo
    {
        return $this->belongsTo(Torneo::class);
    }

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<Pista, $this> */
    public function pista(): BelongsTo
    {
        return $this->belongsTo(Pista::class);
    }
}
