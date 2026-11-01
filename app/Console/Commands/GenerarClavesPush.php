<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

/**
 * Las claves VAPID de las notificaciones push, para el .env. Se generan
 * una sola vez: si se cambian, los dispositivos que ya las aceptaron dejan
 * de recibirlas hasta que se vuelvan a activar. Con --guardar las escribe
 * al final del .env, sin tocar nada más; si ya hay claves, no hace nada.
 */
class GenerarClavesPush extends Command
{
    protected $signature = 'push:claves {--guardar : Escribirlas en el .env en vez de mostrarlas}';

    protected $description = 'Genera las claves VAPID de las notificaciones push (para el .env)';

    public function handle(): int
    {
        $env = base_path('.env');
        $yaHay = filled(config('webpush.clave_publica'))
            || (is_file($env) && preg_match('/^WEBPUSH_VAPID_PUBLIC_KEY=\S+/m', (string) file_get_contents($env)) === 1);

        if ($yaHay && $this->option('guardar')) {
            $this->info('Ya hay claves en el .env: no se cambian (los móviles que ya las aceptaron dejarían de recibir avisos).');

            return self::SUCCESS;
        }

        if ($yaHay) {
            $this->warn('Ya hay claves puestas. Si las cambia, cada dispositivo tendrá que volver a activar las notificaciones.');
        }

        $claves = VAPID::createVapidKeys();
        $lineas = [
            'WEBPUSH_VAPID_PUBLIC_KEY='.$claves['publicKey'],
            'WEBPUSH_VAPID_PRIVATE_KEY='.$claves['privateKey'],
            'WEBPUSH_VAPID_SUBJECT=mailto:'.config()->string('mail.from.address'),
        ];

        if ($this->option('guardar')) {
            if (! is_file($env) || ! is_writable($env)) {
                $this->error('No se puede escribir en el .env.');

                return self::FAILURE;
            }

            $contenido = (string) preg_replace('/^WEBPUSH_VAPID_(PUBLIC_KEY|PRIVATE_KEY|SUBJECT)=\s*$\R?/m', '', (string) file_get_contents($env));
            file_put_contents($env, rtrim($contenido)."\n\n# Notificaciones push (php artisan push:claves)\n".implode("\n", $lineas)."\n");

            $this->info('Claves guardadas en el .env. Ejecute ahora `php artisan optimize` (o despliegue) para que se lean.');

            return self::SUCCESS;
        }

        $this->line('Añada estas líneas al .env:');
        $this->newLine();
        foreach ($lineas as $linea) {
            $this->line($linea);
        }

        return self::SUCCESS;
    }
}
