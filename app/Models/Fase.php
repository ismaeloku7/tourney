<?php

namespace App\Models;

use App\Competicion\Datos\ConfiguracionDeEliminatoria;
use App\Competicion\Datos\ConfiguracionDeGrupos;
use App\Enums\EstadoFase;
use Carbon\CarbonInterface;
use App\Enums\TipoDeFase;
use App\Models\Concerns\VaALaPapelera;
use App\Tenancy\EnLaBaseDelOrganizador;
use Database\Factories\FaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una fase de una categoría: grupos, cuadro, consolación o liga, con su
 * configuración (el DTO del tipo, en JSON) y su estado.
 *
 * @property int $id
 * @property int $categoria_id
 * @property TipoDeFase $tipo
 * @property string $nombre
 * @property int $orden
 * @property array<string, mixed> $configuracion
 * @property int|null $formato_partido_id
 * @property EstadoFase $estado
 * @property int|null $fase_origen_id
 * @property int|null $semilla_sorteo
 * @property CarbonInterface|null $cerrada_en
 */
#[Fillable(['categoria_id', 'tipo', 'nombre', 'orden', 'configuracion', 'formato_partido_id', 'estado', 'fase_origen_id', 'semilla_sorteo', 'cerrada_en'])]
class Fase extends Model
{
    /** @use HasFactory<FaseFactory> */
    use EnLaBaseDelOrganizador, HasFactory, VaALaPapelera;

    protected function casts(): array
    {
        return [
            'tipo' => TipoDeFase::class,
            'orden' => 'integer',
            'configuracion' => 'array',
            'estado' => EstadoFase::class,
            'semilla_sorteo' => 'integer',
            'cerrada_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<Categoria, $this> */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    /** @return BelongsTo<FormatoPartido, $this> */
    public function formatoPartido(): BelongsTo
    {
        return $this->belongsTo(FormatoPartido::class);
    }

    /** @return BelongsTo<Fase, $this> */
    public function faseOrigen(): BelongsTo
    {
        return $this->belongsTo(self::class, 'fase_origen_id');
    }

    /** @return HasMany<Fase, $this> */
    public function fasesDerivadas(): HasMany
    {
        return $this->hasMany(self::class, 'fase_origen_id');
    }

    /** @return HasMany<Grupo, $this> */
    public function grupos(): HasMany
    {
        return $this->hasMany(Grupo::class)->orderBy('orden');
    }

    /** @return HasMany<Partido, $this> */
    public function partidos(): HasMany
    {
        return $this->hasMany(Partido::class);
    }

    /** @return HasMany<Clasificacion, $this> */
    public function clasificaciones(): HasMany
    {
        return $this->hasMany(Clasificacion::class);
    }

    /**
     * La configuración como DTO según el tipo: valida lo guardado, así
     * que una fase con una configuración rota no llega al motor.
     */
    public function configuracion(): ConfiguracionDeGrupos|ConfiguracionDeEliminatoria
    {
        return $this->tipo->esDeGrupos()
            ? ConfiguracionDeGrupos::desdeArray($this->configuracion)
            : ConfiguracionDeEliminatoria::desdeArray($this->configuracion);
    }

    public function configuracionDeGrupos(): ConfiguracionDeGrupos
    {
        return ConfiguracionDeGrupos::desdeArray($this->configuracion);
    }

    public function configuracionDeEliminatoria(): ConfiguracionDeEliminatoria
    {
        return ConfiguracionDeEliminatoria::desdeArray($this->configuracion);
    }

    /** La fase siguiente de la categoría, si la hay. */
    public function siguiente(): ?self
    {
        return self::query()->where('categoria_id', $this->categoria_id)->where('orden', '>', $this->orden)->orderBy('orden')->first();
    }

    /** El formato propio de la fase o, si no, el efectivo de la categoría. */
    public function formatoEfectivo(): FormatoPartido
    {
        return $this->formatoPartido ?? $this->categoria->formatoEfectivo();
    }

    public function tieneResultados(): bool
    {
        return $this->partidos()->whereIn('estado', ['jugado', 'walkover', 'pendiente_validacion', 'en_juego'])->exists();
    }
}
