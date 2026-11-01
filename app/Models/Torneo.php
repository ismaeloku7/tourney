<?php

namespace App\Models;

use App\Enums\EstadoTorneo;
use App\Enums\ResultadosPorJugadores;
use App\Models\Concerns\VaALaPapelera;
use App\Tenancy\EnLaBaseDelOrganizador;
use Carbon\CarbonInterface;
use Database\Factories\TorneoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * Un torneo del organizador, con sus categorías, su equipo, sus pistas y
 * sus franjas. Las reglas de competición están en cada categoría.
 *
 * @property int $id
 * @property string $nombre
 * @property string $slug
 * @property string $deporte
 * @property int|null $sede_id
 * @property string|null $direccion
 * @property CarbonInterface $fecha_inicio
 * @property CarbonInterface $fecha_fin
 * @property CarbonInterface|null $inscripcion_desde
 * @property CarbonInterface|null $inscripcion_hasta
 * @property bool $inscripcion_online
 * @property EstadoTorneo $estado
 * @property bool $listado_en_plataforma
 * @property ResultadosPorJugadores $resultados_por_jugadores
 * @property int $minutos_para_validar
 * @property bool $privacidad_alias
 * @property string|null $cartel_path
 * @property string|null $normativa
 * @property string|null $contacto
 * @property int $descanso_minimo_minutos
 * @property int $margen_entre_partidos_minutos
 * @property array<string, mixed>|null $campos_extra
 * @property CarbonInterface|null $publicado_en
 * @property CarbonInterface|null $horarios_publicados_en
 * @property CarbonInterface|null $finalizado_en
 * @property CarbonInterface|null $archivado_en
 * @property int|null $creado_por
 */
#[Fillable(['nombre', 'slug', 'deporte', 'sede_id', 'direccion', 'fecha_inicio', 'fecha_fin', 'inscripcion_desde', 'inscripcion_hasta', 'inscripcion_online', 'estado', 'listado_en_plataforma', 'resultados_por_jugadores', 'minutos_para_validar', 'privacidad_alias', 'cartel_path', 'normativa', 'contacto', 'descanso_minimo_minutos', 'margen_entre_partidos_minutos', 'campos_extra', 'publicado_en', 'horarios_publicados_en', 'finalizado_en', 'archivado_en', 'creado_por'])]
class Torneo extends Model
{
    /** @use HasFactory<TorneoFactory> */
    use EnLaBaseDelOrganizador, HasFactory, VaALaPapelera;

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'inscripcion_desde' => 'datetime',
            'inscripcion_hasta' => 'datetime',
            'inscripcion_online' => 'boolean',
            'estado' => EstadoTorneo::class,
            'listado_en_plataforma' => 'boolean',
            'resultados_por_jugadores' => ResultadosPorJugadores::class,
            'minutos_para_validar' => 'integer',
            'privacidad_alias' => 'boolean',
            'descanso_minimo_minutos' => 'integer',
            'margen_entre_partidos_minutos' => 'integer',
            'campos_extra' => 'array',
            'publicado_en' => 'datetime',
            'horarios_publicados_en' => 'datetime',
            'finalizado_en' => 'datetime',
            'archivado_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<Sede, $this> */
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    /** @return HasMany<Categoria, $this> */
    public function categorias(): HasMany
    {
        return $this->hasMany(Categoria::class)->orderBy('orden');
    }

    /**
     * Las inscripciones de todas sus categorías.
     *
     * @return HasManyThrough<Inscripcion, Categoria, $this>
     */
    public function inscripciones(): HasManyThrough
    {
        return $this->hasManyThrough(Inscripcion::class, Categoria::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function administradores(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'torneo_administradores')->withTimestamps();
    }

    /** @return HasMany<TorneoArbitro, $this> */
    public function arbitros(): HasMany
    {
        return $this->hasMany(TorneoArbitro::class);
    }

    /** @return HasMany<TorneoPista, $this> */
    public function torneoPistas(): HasMany
    {
        return $this->hasMany(TorneoPista::class)->orderBy('orden');
    }

    /** @return BelongsToMany<Pista, $this> */
    public function pistas(): BelongsToMany
    {
        return $this->belongsToMany(Pista::class, 'torneo_pistas')->withPivot(['orden', 'disponibilidad'])->orderByPivot('orden');
    }

    /** @return HasMany<Franja, $this> */
    public function franjas(): HasMany
    {
        return $this->hasMany(Franja::class)->orderBy('inicio');
    }

    /** @return HasMany<Disponibilidad, $this> */
    public function disponibilidades(): HasMany
    {
        return $this->hasMany(Disponibilidad::class);
    }

    public function inscripcionAbierta(): bool
    {
        return $this->estado === EstadoTorneo::InscripcionAbierta;
    }

    /** Si la web ofrece inscribirse: la inscripción está abierta y no la hace solo la organización. */
    public function admiteInscripcionOnline(): bool
    {
        return $this->inscripcionAbierta() && $this->inscripcion_online;
    }
}
