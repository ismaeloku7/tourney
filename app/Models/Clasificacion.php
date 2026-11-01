<?php

namespace App\Models;

use App\Enums\CriterioDeDesempate;
use App\Tenancy\EnLaBaseDelOrganizador;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * La línea materializada de un participante en la clasificación de su
 * grupo: se recalcula al guardar o anular un resultado, para que las
 * páginas públicas no calculen nada.
 *
 * @property int $id
 * @property int $fase_id
 * @property int|null $grupo_id
 * @property int $participante_id
 * @property int $posicion
 * @property int $puntos
 * @property int $pj
 * @property int $pg
 * @property int $pp
 * @property int $sets_favor
 * @property int $sets_contra
 * @property int $juegos_favor
 * @property int $juegos_contra
 * @property CriterioDeDesempate|null $desempate_aplicado
 * @property int|null $sorteo_desempate
 * @property string|null $clasificado_como
 * @property Carbon|null $calculada_en
 */
#[Fillable(['fase_id', 'grupo_id', 'participante_id', 'posicion', 'puntos', 'pj', 'pg', 'pp', 'sets_favor', 'sets_contra', 'juegos_favor', 'juegos_contra', 'desempate_aplicado', 'sorteo_desempate', 'clasificado_como', 'calculada_en'])]
class Clasificacion extends Model
{
    use EnLaBaseDelOrganizador;

    public $timestamps = false;

    protected $table = 'clasificaciones';

    protected function casts(): array
    {
        return [
            'posicion' => 'integer',
            'puntos' => 'integer',
            'pj' => 'integer',
            'pg' => 'integer',
            'pp' => 'integer',
            'sets_favor' => 'integer',
            'sets_contra' => 'integer',
            'juegos_favor' => 'integer',
            'juegos_contra' => 'integer',
            'desempate_aplicado' => CriterioDeDesempate::class,
            'sorteo_desempate' => 'integer',
            'calculada_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<Fase, $this> */
    public function fase(): BelongsTo
    {
        return $this->belongsTo(Fase::class);
    }

    /** @return BelongsTo<Grupo, $this> */
    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class);
    }

    /** @return BelongsTo<Participante, $this> */
    public function participante(): BelongsTo
    {
        return $this->belongsTo(Participante::class);
    }
}
