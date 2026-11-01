<?php

namespace App\Models;

use App\Tenancy\EnLaBaseDelOrganizador;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un enlace de acceso del jugador. Solo se guarda el hash del token.
 *
 * @property int $id
 * @property int $jugador_id
 * @property string $token
 * @property CarbonInterface $expira_en
 * @property CarbonInterface|null $usado_en
 * @property CarbonInterface|null $created_at
 */
#[Fillable(['jugador_id', 'token', 'expira_en', 'usado_en'])]
class EnlaceMagico extends Model
{
    use EnLaBaseDelOrganizador;

    public const UPDATED_AT = null;

    protected $table = 'enlaces_magicos';

    protected function casts(): array
    {
        return [
            'expira_en' => 'datetime',
            'usado_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<Jugador, $this> */
    public function jugador(): BelongsTo
    {
        return $this->belongsTo(Jugador::class);
    }
}
