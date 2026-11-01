<?php

namespace App\Models;

use App\Tenancy\EnLaBaseDelOrganizador;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Una entrada del registro de cambios: quién hizo qué sobre qué, con el
 * antes y el después. No se edita.
 *
 * @property int $id
 * @property int|null $user_id
 * @property int|null $jugador_id
 * @property string $accion
 * @property string $sujeto_type
 * @property int $sujeto_id
 * @property array<string, mixed>|null $antes
 * @property array<string, mixed>|null $despues
 */
#[Fillable(['user_id', 'jugador_id', 'accion', 'sujeto_type', 'sujeto_id', 'antes', 'despues'])]
class RegistroDeCambio extends Model
{
    use EnLaBaseDelOrganizador;

    public const UPDATED_AT = null;

    protected $table = 'registro_de_cambios';

    protected function casts(): array
    {
        return [
            'antes' => 'array',
            'despues' => 'array',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function sujeto(): MorphTo
    {
        return $this->morphTo('sujeto');
    }

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<Jugador, $this> */
    public function jugador(): BelongsTo
    {
        return $this->belongsTo(Jugador::class);
    }

    /**
     * @param  array<string, mixed>|null  $antes
     * @param  array<string, mixed>|null  $despues
     */
    public static function anotar(string $accion, Model $sujeto, User|Jugador|null $quien, ?array $antes, ?array $despues): self
    {
        return self::query()->create([
            'user_id' => $quien instanceof User ? $quien->id : null,
            'jugador_id' => $quien instanceof Jugador ? $quien->id : null,
            'accion' => $accion,
            'sujeto_type' => $sujeto->getMorphClass(),
            'sujeto_id' => $sujeto->getKey(),
            'antes' => $antes,
            'despues' => $despues,
        ]);
    }
}
