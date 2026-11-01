<?php

namespace App\Models;

use App\Tenancy\EnLaBaseDelOrganizador;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Un móvil u ordenador que ha aceptado notificaciones push: de un jugador,
 * de alguien del equipo o de nadie (quien sigue un torneo sin cuenta).
 * Las claves son del navegador: con ellas se cifra cada aviso para ese
 * dispositivo.
 *
 * @property int $id
 * @property string $endpoint
 * @property string $endpoint_hash
 * @property string $clave_p256dh
 * @property string $clave_auth
 * @property int|null $jugador_id
 * @property int|null $user_id
 * @property string|null $dispositivo
 * @property CarbonInterface|null $ultimo_envio
 */
#[Fillable(['endpoint', 'endpoint_hash', 'clave_p256dh', 'clave_auth', 'jugador_id', 'user_id', 'dispositivo', 'ultimo_envio'])]
#[Hidden(['endpoint', 'clave_p256dh', 'clave_auth'])]
class SuscripcionPush extends Model
{
    use EnLaBaseDelOrganizador;

    protected $table = 'push_suscripciones';

    protected function casts(): array
    {
        return ['ultimo_envio' => 'datetime'];
    }

    /** @return BelongsTo<Jugador, $this> */
    public function jugador(): BelongsTo
    {
        return $this->belongsTo(Jugador::class);
    }

    /** @return BelongsToMany<Torneo, $this> */
    public function torneos(): BelongsToMany
    {
        return $this->belongsToMany(Torneo::class, 'push_suscripcion_torneos', 'suscripcion_id', 'torneo_id');
    }
}
