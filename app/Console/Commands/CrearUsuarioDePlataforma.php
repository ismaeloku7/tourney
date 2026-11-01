<?php

namespace App\Console\Commands;

use App\Models\UsuarioPlataforma;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * El primer usuario de la plataforma: sin él no hay quién entre en
 * /plataforma para crear los demás.
 */
class CrearUsuarioDePlataforma extends Command
{
    protected $signature = 'plataforma:administrador';

    protected $description = 'Da de alta un usuario de la plataforma (panel /plataforma)';

    public function handle(): int
    {
        $nombre = text('Nombre', required: true);
        $email = text('Correo electrónico', required: true);
        $clave = password('Contraseña (al menos 12 caracteres)', required: true);

        $validador = Validator::make(
            ['email' => $email, 'password' => $clave],
            ['email' => ['email', 'unique:usuarios_plataforma,email'], 'password' => [Password::default()]],
        );

        if ($validador->fails()) {
            foreach ($validador->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        UsuarioPlataforma::query()->create(['nombre' => $nombre, 'email' => $email, 'password' => $clave, 'activo' => true]);

        $this->components->info('Usuario de plataforma creado. Entre en /plataforma del dominio del SaaS.');

        return self::SUCCESS;
    }
}
