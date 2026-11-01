<?php

namespace App\Models;

use Database\Factories\UsuarioPlataformaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Usuario de la plataforma: entra en /plataforma con el guard
 * `plataforma`. Vive en la base central (CentralConnection), también si
 * hay un organizador activo. No es `User`: nadie del equipo de un
 * organizador puede entrar en la plataforma.
 *
 * @property int $id
 * @property string $nombre
 * @property string $email
 * @property string $password
 * @property bool $activo
 * @property Carbon|null $ultimo_acceso
 */
#[Fillable(['nombre', 'email', 'password', 'activo'])]
#[Hidden(['password', 'remember_token'])]
class UsuarioPlataforma extends Authenticatable
{
    /** @use HasFactory<UsuarioPlataformaFactory> */
    use CentralConnection, HasFactory, Notifiable;

    protected $table = 'usuarios_plataforma';

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'ultimo_acceso' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::set(fn (string $valor): string => Str::lower(trim($valor)));
    }
}
