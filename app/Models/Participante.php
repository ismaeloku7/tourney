<?php

namespace App\Models;

use App\Enums\EstadoParticipante;
use App\Models\Concerns\VaALaPapelera;
use App\Tenancy\EnLaBaseDelOrganizador;
use Database\Factories\ParticipanteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Quien compite en una categoría: la pareja, en pádel («pareja» solo en
 * los textos de interfaz). Tiene sus jugadores, su inscripción y, tras el
 * sorteo, su grupo y sus partidos.
 *
 * @property int $id
 * @property int $categoria_id
 * @property string $nombre
 * @property int|null $cabeza_de_serie
 * @property EstadoParticipante $estado
 */
#[Fillable(['categoria_id', 'nombre', 'cabeza_de_serie', 'estado'])]
class Participante extends Model
{
    /** @use HasFactory<ParticipanteFactory> */
    use EnLaBaseDelOrganizador, HasFactory, VaALaPapelera;

    protected function casts(): array
    {
        return [
            'cabeza_de_serie' => 'integer',
            'estado' => EstadoParticipante::class,
        ];
    }

    /** @return BelongsTo<Categoria, $this> */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    /** @return BelongsToMany<Jugador, $this> */
    public function jugadores(): BelongsToMany
    {
        return $this->belongsToMany(Jugador::class, 'participante_jugadores')->withPivot(['orden', 'confirmado_en', 'invitacion_token', 'invitacion_expira_en'])->withTimestamps()->orderByPivot('orden');
    }

    /** @return HasMany<ParticipanteJugador, $this> */
    public function participanteJugadores(): HasMany
    {
        return $this->hasMany(ParticipanteJugador::class)->orderBy('orden');
    }

    /** @return HasOne<Inscripcion, $this> */
    public function inscripcion(): HasOne
    {
        return $this->hasOne(Inscripcion::class);
    }

    /** @return BelongsToMany<Grupo, $this> */
    public function grupos(): BelongsToMany
    {
        return $this->belongsToMany(Grupo::class, 'grupo_participantes')->withPivot('posicion_sorteo');
    }

    /** @return HasMany<Clasificacion, $this> */
    public function clasificaciones(): HasMany
    {
        return $this->hasMany(Clasificacion::class);
    }

    /** «García / López», a partir de los apellidos de sus jugadores. */
    public static function nombreDesdeJugadores(Jugador ...$jugadores): string
    {
        return implode(' / ', array_map(fn (Jugador $jugador): string => $jugador->apellidos !== '' ? $jugador->apellidos : $jugador->nombre, $jugadores));
    }

    /**
     * Los ids de sus jugadores, para que el planificador sepa quién juega
     * en dos categorías.
     *
     * @return list<int>
     */
    public function idsDeJugadores(): array
    {
        return array_values($this->participanteJugadores->pluck('jugador_id')->map(fn (mixed $id): int => (int) $id)->all());
    }
}
