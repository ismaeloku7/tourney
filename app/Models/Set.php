<?php

namespace App\Models;

use App\Tenancy\EnLaBaseDelOrganizador;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un set de un partido, con el tie-break si lo hubo.
 *
 * @property int $id
 * @property int $partido_id
 * @property int $numero
 * @property int $juegos_local
 * @property int $juegos_visitante
 * @property int|null $tie_break_local
 * @property int|null $tie_break_visitante
 * @property bool $es_super_tie_break
 */
#[Fillable(['partido_id', 'numero', 'juegos_local', 'juegos_visitante', 'tie_break_local', 'tie_break_visitante', 'es_super_tie_break'])]
class Set extends Model
{
    use EnLaBaseDelOrganizador;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'juegos_local' => 'integer',
            'juegos_visitante' => 'integer',
            'tie_break_local' => 'integer',
            'tie_break_visitante' => 'integer',
            'es_super_tie_break' => 'boolean',
        ];
    }

    /** @return BelongsTo<Partido, $this> */
    public function partido(): BelongsTo
    {
        return $this->belongsTo(Partido::class);
    }
}
