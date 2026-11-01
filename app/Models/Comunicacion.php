<?php

namespace App\Models;

use App\Tenancy\EnLaBaseDelOrganizador;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un aviso libre de la organización a los inscritos de un torneo (o de una
 * categoría), por correo, push o los dos.
 *
 * @property int $id
 * @property int $torneo_id
 * @property int|null $categoria_id
 * @property string $titulo
 * @property string $cuerpo
 * @property list<string> $canales
 * @property int|null $enviada_por
 * @property CarbonInterface|null $enviada_en
 * @property int $destinatarios
 */
#[Fillable(['torneo_id', 'categoria_id', 'titulo', 'cuerpo', 'canales', 'enviada_por', 'enviada_en', 'destinatarios'])]
class Comunicacion extends Model
{
    use EnLaBaseDelOrganizador;

    protected $table = 'comunicaciones';

    protected function casts(): array
    {
        return [
            'canales' => 'array',
            'enviada_en' => 'datetime',
            'destinatarios' => 'integer',
        ];
    }

    /** @return BelongsTo<Torneo, $this> */
    public function torneo(): BelongsTo
    {
        return $this->belongsTo(Torneo::class);
    }

    /** @return BelongsTo<Categoria, $this> */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }
}
