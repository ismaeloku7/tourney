<?php

namespace App\Models;

use App\Tenancy\EnLaBaseDelOrganizador;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * El enlace de acceso directo de una pareja: entra uno de sus jugadores
 * sin correo ni contraseña. No caduca; se borra a mano. El token va
 * cifrado (para enseñarlo otra vez) y se busca por su hash.
 *
 * @property int $id
 * @property int $inscripcion_id
 * @property int $jugador_id
 * @property string $token
 * @property string $token_hash
 * @property int|null $creado_por
 * @property CarbonInterface|null $ultimo_uso_en
 * @property CarbonInterface|null $created_at
 */
#[Fillable(['inscripcion_id', 'jugador_id', 'token', 'token_hash', 'creado_por', 'ultimo_uso_en'])]
class AccesoDirecto extends Model
{
    use EnLaBaseDelOrganizador;

    protected $table = 'accesos_directos';

    protected $hidden = ['token', 'token_hash'];

    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'ultimo_uso_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<Inscripcion, $this> */
    public function inscripcion(): BelongsTo
    {
        return $this->belongsTo(Inscripcion::class);
    }

    /** @return BelongsTo<Jugador, $this> */
    public function jugador(): BelongsTo
    {
        return $this->belongsTo(Jugador::class);
    }
}
