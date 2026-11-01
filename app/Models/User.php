<?php

namespace App\Models;

use App\Enums\Rol;
use App\Models\Concerns\VaALaPapelera;
use App\Tenancy\EnLaBaseDelOrganizador;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Una persona del equipo de un organizador: propietario, administrador
 * de torneo o árbitro. Los jugadores no son `User`.
 *
 * Vive en la base del organizador (`EnLaBaseDelOrganizador`): el guard
 * solo encuentra al equipo del organizador del host, así que una sesión
 * abierta en un organizador no vale en otro.
 *
 * @property int $id
 * @property string $nombre
 * @property string $apellidos
 * @property string $email
 * @property string|null $telefono
 * @property Rol $rol
 * @property bool $activo
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property Carbon|null $ultimo_acceso
 * @property-read string $nombre_completo
 */
#[Fillable(['nombre', 'apellidos', 'email', 'telefono', 'rol', 'activo', 'email_verified_at', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasName
{
    /** @use HasFactory<UserFactory> */
    use EnLaBaseDelOrganizador, HasFactory, Notifiable, VaALaPapelera;

    protected function casts(): array
    {
        return [
            'rol' => Rol::class,
            'activo' => 'boolean',
            'email_verified_at' => 'datetime',
            'ultimo_acceso' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * El correo se guarda en minúsculas: es único dentro del organizador.
     *
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::set(fn (string $valor): string => Str::lower(trim($valor)));
    }

    /**
     * @return Attribute<string, never>
     */
    protected function nombreCompleto(): Attribute
    {
        return Attribute::get(fn (): string => trim($this->nombre.' '.$this->apellidos));
    }

    public function esPropietario(): bool
    {
        return $this->rol === Rol::Propietario;
    }

    /**
     * La base es la del organizador del host: el equipo de otros ni
     * aparece. Entran el propietario y los administradores de torneo
     * activos; un árbitro trabaja desde la mesa, no desde el panel. Se
     * mira en cada petición: desactivar a alguien lo saca aunque tenga la
     * sesión abierta.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'panel' && $this->activo && $this->rol !== Rol::Arbitro;
    }

    public function getFilamentName(): string
    {
        return $this->nombre_completo;
    }

    /** Los torneos que administra (solo cuenta para el rol administrador). */
    public function torneosQueAdministra(): BelongsToMany
    {
        return $this->belongsToMany(Torneo::class, 'torneo_administradores')->withTimestamps();
    }
}
