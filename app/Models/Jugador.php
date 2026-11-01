<?php

namespace App\Models;

use App\Enums\Genero;
use App\Models\Concerns\VaALaPapelera;
use App\Tenancy\EnLaBaseDelOrganizador;
use Database\Factories\JugadorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Un jugador del organizador: entra en el portal del jugador con el guard
 * `jugador`. No es `User`. En público solo salen nombre y apellidos, o el
 * alias si el torneo lo pide; nunca teléfono ni correo.
 *
 * @property int $id
 * @property string $nombre
 * @property string $apellidos
 * @property string|null $email
 * @property string|null $telefono
 * @property Carbon|null $fecha_nacimiento
 * @property Genero|null $genero
 * @property string|null $nivel_declarado
 * @property string|null $alias
 * @property string|null $password
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $consentimiento_en
 * @property array<string, bool>|null $avisos
 * @property string|null $identificador_plataforma
 * @property Carbon|null $anonimizado_en
 * @property int|null $fusionado_en_id
 * @property Carbon|null $ultimo_acceso
 * @property-read string $nombre_completo
 */
#[Fillable(['nombre', 'apellidos', 'email', 'telefono', 'fecha_nacimiento', 'genero', 'nivel_declarado', 'alias', 'password', 'email_verified_at', 'consentimiento_en', 'avisos', 'identificador_plataforma'])]
#[Hidden(['password', 'remember_token', 'email', 'telefono'])]
class Jugador extends Authenticatable
{
    /** @use HasFactory<JugadorFactory> */
    use EnLaBaseDelOrganizador, HasFactory, Notifiable, VaALaPapelera;

    protected $table = 'jugadores';

    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
            'genero' => Genero::class,
            'email_verified_at' => 'datetime',
            'consentimiento_en' => 'datetime',
            'avisos' => 'array',
            'anonimizado_en' => 'datetime',
            'ultimo_acceso' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * @return Attribute<string|null, string|null>
     */
    protected function email(): Attribute
    {
        return Attribute::set(fn (?string $valor): ?string => $valor === null ? null : Str::lower(trim($valor)));
    }

    /**
     * @return Attribute<string, never>
     */
    protected function nombreCompleto(): Attribute
    {
        return Attribute::get(fn (): string => trim($this->nombre.' '.$this->apellidos));
    }

    /** Lo que se enseña en público: el alias si el torneo lo pide y lo tiene. */
    public function nombrePublico(bool $conAlias): string
    {
        return $conAlias && $this->alias !== null && $this->alias !== '' ? $this->alias : $this->nombre_completo;
    }

    /** Menor de 14 años: hace falta la autorización de un tutor. */
    public function esMenorDeCatorce(): bool
    {
        return $this->fecha_nacimiento !== null && $this->fecha_nacimiento->age < 14;
    }

    /** @return BelongsToMany<Participante, $this> */
    public function participantes(): BelongsToMany
    {
        return $this->belongsToMany(Participante::class, 'participante_jugadores')->withPivot(['orden', 'confirmado_en'])->withTimestamps();
    }

    /** @return HasMany<EnlaceMagico, $this> */
    public function enlacesMagicos(): HasMany
    {
        return $this->hasMany(EnlaceMagico::class);
    }

    /** @return HasMany<Disponibilidad, $this> */
    public function disponibilidades(): HasMany
    {
        return $this->hasMany(Disponibilidad::class);
    }

    /** @return BelongsTo<Jugador, $this> */
    public function fusionadoEn(): BelongsTo
    {
        return $this->belongsTo(self::class, 'fusionado_en_id');
    }
}
