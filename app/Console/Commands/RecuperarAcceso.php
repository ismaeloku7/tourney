<?php

namespace App\Console\Commands;

use App\Enums\Ajuste;
use App\Models\Organizador;
use App\Models\User;
use App\Models\ValorDeAjuste;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Para cuando alguien del equipo no puede entrar y el correo de
 * restablecer no le llega: le pone una contraseña nueva (que enseña una
 * sola vez), lo activa y le quita los bloqueos por intentos fallidos. Con
 * --sin-correo-propio, además, el organizador vuelve a mandar por el
 * servidor de la plataforma (los datos del suyo se conservan).
 */
class RecuperarAcceso extends Command
{
    protected $signature = 'equipo:recuperar-acceso
        {organizador : El slug del organizador (ceas, por ejemplo)}
        {email : El correo con el que entra en el panel}
        {--sin-correo-propio : Desactivar su servidor de correo propio}
        {--clave= : La contraseña nueva (al menos 12 caracteres); sin ella se genera una}';

    protected $description = 'Contraseña nueva para alguien del equipo, sin depender del correo';

    public function handle(): int
    {
        $elegida = $this->option('clave');
        if (is_string($elegida) && mb_strlen($elegida) < 12) {
            $this->line('ERROR: la contraseña de --clave tiene que tener al menos 12 caracteres.');

            return self::FAILURE;
        }

        $this->line('Buscando el organizador '.$this->argument('organizador').'...');
        $organizador = Organizador::query()->find((string) $this->argument('organizador'));

        if ($organizador === null) {
            $this->line('ERROR: no hay ningún organizador con ese slug. Hay: '.Organizador::query()->pluck('id')->implode(', '));

            return self::FAILURE;
        }

        $email = Str::lower(trim((string) $this->argument('email')));

        /** @var string|null $clave */
        $clave = $organizador->run(function () use ($email, $elegida): ?string {
            $usuario = User::query()->where('email', $email)->first();

            if ($usuario === null) {
                $this->line('ERROR: no hay nadie en el equipo con ese correo. Hay: '.User::query()->pluck('email')->implode(', '));

                return null;
            }

            $this->line("Cambiando la contraseña de {$email}...");
            $clave = is_string($elegida) ? $elegida : Str::password(16, symbols: false);
            // Con la contraseña nueva, fuera las sesiones recordadas en otros dispositivos.
            $usuario->forceFill(['password' => $clave, 'activo' => true, 'remember_token' => Str::random(60)])->save();
            $this->line('Contraseña guardada y cuenta activa.');

            if ($this->option('sin-correo-propio') && ValorDeAjuste::obtener(Ajuste::CorreoPropio) === true) {
                ValorDeAjuste::fijar([Ajuste::CorreoPropio->value => false]);
                $this->line('Servidor de correo propio desactivado: los correos salen por el de la plataforma. Se vuelve a activar en Ajustes › Correo.');
            }

            return $clave;
        });

        if ($clave === null) {
            return self::FAILURE;
        }

        $bloqueos = $this->quitarBloqueos($email);

        $this->line("Bloqueos por intentos fallidos quitados: {$bloqueos}.");
        $this->line(is_string($elegida)
            ? "Listo: ya puede entrar como {$email} con la contraseña que ha puesto en --clave."
            : "Contraseña nueva de {$email}: {$clave}  (cámbiela al entrar, desde su perfil)");

        return self::SUCCESS;
    }

    /** Los intentos fallidos de entrar con ese correo, desde cualquier sitio (la caché va en la base central). */
    private function quitarBloqueos(string $email): int
    {
        if (config('cache.default') !== 'database') {
            return 0;
        }

        return DB::connection(config()->string('cache.stores.database.connection', config()->string('database.default')))
            ->table(config()->string('cache.stores.database.table', 'cache'))
            ->where('key', 'like', '%login-equipo:%|'.$email.'|%')
            ->delete();
    }
}
