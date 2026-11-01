<?php

namespace App\Models;

use App\Tenancy\EnLaBaseDelOrganizador;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un jugador dentro de un participante, con el orden (1 o 2 en pádel) y
 * si ha confirmado la invitación de su compañero.
 *
 * @property int $id
 * @property int $participante_id
 * @property int $jugador_id
 * @property int $orden
 * @property Carbon|null $confirmado_en
 * @property string|null $invitacion_token
 * @property Carbon|null $invitacion_expira_en
 */
#[Fillable(['participante_id', 'jugador_id', 'orden', 'confirmado_en', 'invitacion_token', 'invitacion_expira_en'])]
class ParticipanteJugador extends Model
{
    use EnLaBaseDelOrganizador;

    protected $table = 'participante_jugadores';

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'confirmado_en' => 'datetime',
            'invitacion_expira_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<Participante, $this> */
    public function participante(): BelongsTo
    {
        return $this->belongsTo(Participante::class);
    }

    /** @return BelongsTo<Jugador, $this> */
    public function jugador(): BelongsTo
    {
        return $this->belongsTo(Jugador::class);
    }
}
