<?php

namespace App\Models;

use App\Enums\Genero;
use App\Enums\Modalidad;
use App\Models\Concerns\VaALaPapelera;
use App\Tenancy\EnLaBaseDelOrganizador;
use Database\Factories\CategoriaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una categoría de un torneo (Masculina 3ª, Mixta B…): sus participantes,
 * sus inscripciones y sus fases. El orden es también la prioridad del
 * planificador.
 *
 * @property int $id
 * @property int $torneo_id
 * @property string $nombre
 * @property Modalidad $modalidad
 * @property Genero $genero
 * @property string|null $nivel
 * @property int|null $cupo
 * @property int $minimo_participantes
 * @property string|null $precio
 * @property int|null $formato_partido_id
 * @property int $orden
 */
#[Fillable(['torneo_id', 'nombre', 'modalidad', 'genero', 'nivel', 'cupo', 'minimo_participantes', 'precio', 'formato_partido_id', 'orden'])]
class Categoria extends Model
{
    /** @use HasFactory<CategoriaFactory> */
    use EnLaBaseDelOrganizador, HasFactory, VaALaPapelera;

    protected function casts(): array
    {
        return [
            'modalidad' => Modalidad::class,
            'genero' => Genero::class,
            'cupo' => 'integer',
            'minimo_participantes' => 'integer',
            'precio' => 'decimal:2',
            'orden' => 'integer',
        ];
    }

    /** @return BelongsTo<Torneo, $this> */
    public function torneo(): BelongsTo
    {
        return $this->belongsTo(Torneo::class);
    }

    /** @return BelongsTo<FormatoPartido, $this> */
    public function formatoPartido(): BelongsTo
    {
        return $this->belongsTo(FormatoPartido::class);
    }

    /** @return HasMany<Participante, $this> */
    public function participantes(): HasMany
    {
        return $this->hasMany(Participante::class);
    }

    /** @return HasMany<Inscripcion, $this> */
    public function inscripciones(): HasMany
    {
        return $this->hasMany(Inscripcion::class);
    }

    /** @return HasMany<Fase, $this> */
    public function fases(): HasMany
    {
        return $this->hasMany(Fase::class)->orderBy('orden');
    }

    /**
     * El formato de la categoría o, si no tiene, el predeterminado del
     * organizador.
     */
    public function formatoEfectivo(): FormatoPartido
    {
        return $this->formatoPartido
            ?? FormatoPartido::query()->where('es_predeterminado', true)->first()
            ?? FormatoPartido::query()->orderBy('id')->firstOrFail();
    }
}
