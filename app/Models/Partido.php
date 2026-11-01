<?php

namespace App\Models;

use App\Competicion\Datos\Origen;
use App\Competicion\Datos\Partido as PartidoDto;
use App\Competicion\Datos\Resultado;
use App\Competicion\Datos\Set as SetDto;
use App\Enums\EstadoPartido;
use App\Enums\ResultadoEspecial;
use App\Models\Concerns\VaALaPapelera;
use App\Tenancy\EnLaBaseDelOrganizador;
use Database\Factories\PartidoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Un partido de una fase: de grupo (con jornada) o de cuadro (con ronda y
 * posición, y los orígenes de cada hueco mientras no se sepa quién lo
 * ocupa). Su resultado son sus sets.
 *
 * @property int $id
 * @property int $fase_id
 * @property int|null $grupo_id
 * @property int|null $jornada
 * @property int|null $ronda
 * @property int|null $posicion
 * @property bool $es_tercer_puesto
 * @property int|null $local_id
 * @property int|null $visitante_id
 * @property array{tipo: string, referencia: int, posicion: int|null}|null $origen_local
 * @property array{tipo: string, referencia: int, posicion: int|null}|null $origen_visitante
 * @property int|null $formato_partido_id
 * @property int|null $pista_id
 * @property int|null $franja_id
 * @property EstadoPartido $estado
 * @property ResultadoEspecial|null $especial
 * @property int|null $ganador_id
 * @property int|null $registrado_por_jugador_id
 * @property Carbon|null $registrado_en
 * @property Carbon|null $validacion_vence_en
 * @property Carbon|null $validado_en
 * @property int|null $validado_por
 * @property string|null $notas
 */
#[Fillable(['fase_id', 'grupo_id', 'jornada', 'ronda', 'posicion', 'es_tercer_puesto', 'local_id', 'visitante_id', 'origen_local', 'origen_visitante', 'formato_partido_id', 'pista_id', 'franja_id', 'estado', 'especial', 'ganador_id', 'registrado_por_jugador_id', 'registrado_en', 'validacion_vence_en', 'validado_en', 'validado_por', 'notas'])]
class Partido extends Model
{
    /** @use HasFactory<PartidoFactory> */
    use EnLaBaseDelOrganizador, HasFactory, VaALaPapelera;

    protected function casts(): array
    {
        return [
            'jornada' => 'integer',
            'ronda' => 'integer',
            'posicion' => 'integer',
            'es_tercer_puesto' => 'boolean',
            'origen_local' => 'array',
            'origen_visitante' => 'array',
            'estado' => EstadoPartido::class,
            'especial' => ResultadoEspecial::class,
            'registrado_en' => 'datetime',
            'validacion_vence_en' => 'datetime',
            'validado_en' => 'datetime',
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
    public function local(): BelongsTo
    {
        return $this->belongsTo(Participante::class, 'local_id');
    }

    /** @return BelongsTo<Participante, $this> */
    public function visitante(): BelongsTo
    {
        return $this->belongsTo(Participante::class, 'visitante_id');
    }

    /** @return BelongsTo<Participante, $this> */
    public function ganador(): BelongsTo
    {
        return $this->belongsTo(Participante::class, 'ganador_id');
    }

    /** @return BelongsTo<FormatoPartido, $this> */
    public function formatoPartido(): BelongsTo
    {
        return $this->belongsTo(FormatoPartido::class);
    }

    /** @return BelongsTo<Pista, $this> */
    public function pista(): BelongsTo
    {
        return $this->belongsTo(Pista::class);
    }

    /** @return BelongsTo<Franja, $this> */
    public function franja(): BelongsTo
    {
        return $this->belongsTo(Franja::class);
    }

    /** @return HasMany<Set, $this> */
    public function sets(): HasMany
    {
        return $this->hasMany(Set::class)->orderBy('numero');
    }

    /** El formato propio de la ronda o, si no, el efectivo de la fase. */
    public function formatoEfectivo(): FormatoPartido
    {
        return $this->formatoPartido ?? $this->fase->formatoEfectivo();
    }

    public function cerrado(): bool
    {
        return $this->estado->cerrado();
    }

    public function origenLocalDto(): ?Origen
    {
        return $this->origen_local === null ? null : Origen::desdeArray($this->origen_local);
    }

    public function origenVisitanteDto(): ?Origen
    {
        return $this->origen_visitante === null ? null : Origen::desdeArray($this->origen_visitante);
    }

    /** Los sets guardados como resultado del motor; nulo si no hay. */
    public function resultadoDto(): ?Resultado
    {
        $sets = $this->sets->map(fn (Set $set): SetDto => new SetDto(
            $set->juegos_local,
            $set->juegos_visitante,
            $set->tie_break_local,
            $set->tie_break_visitante,
            $set->es_super_tie_break,
        ))->values()->all();

        if ($sets === [] && $this->especial === null) {
            return null;
        }

        return new Resultado($sets, $this->especial, $this->ganador_id === null ? null : $this->ganador_id === $this->local_id);
    }

    /** El partido tal y como lo ve el motor. */
    public function aDto(): PartidoDto
    {
        return new PartidoDto(
            id: $this->id,
            grupo: $this->grupo_id,
            jornada: $this->jornada,
            ronda: $this->ronda,
            posicion: $this->posicion,
            esTercerPuesto: $this->es_tercer_puesto,
            local: $this->local_id,
            visitante: $this->visitante_id,
            origenLocal: $this->origenLocalDto(),
            origenVisitante: $this->origenVisitanteDto(),
            estado: $this->estado,
            ganador: $this->ganador_id,
            resultado: $this->resultadoDto(),
        );
    }

    /** El perdedor, cuando hay ganador y los dos huecos están ocupados. */
    public function perdedorId(): ?int
    {
        if ($this->ganador_id === null || $this->local_id === null || $this->visitante_id === null) {
            return null;
        }

        return $this->ganador_id === $this->local_id ? $this->visitante_id : $this->local_id;
    }
}
