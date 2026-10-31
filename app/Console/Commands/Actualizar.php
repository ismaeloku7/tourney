<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que hay que ejecutar tras cada despliegue, en un solo comando (el
 * script de Forge lo llama): migra la base central y la de cada
 * organizador. Para en el primero que falle.
 */
class Actualizar extends Command
{
    protected $signature = 'actualizar';

    protected $description = 'Tras desplegar: migra la base central y la de cada organizador';

    public function handle(): int
    {
        $pasos = [
            'Base central' => ['migrate', ['--force' => true]],
        ];

        // Hasta que la central tenga la tabla de organizadores (llega con
        // la segunda entrega de la fase 1) no hay bases de organizador.
        if (Schema::hasTable('organizadores')) {
            $pasos['Bases de los organizadores'] = ['tenants:migrate', ['--force' => true]];
        }

        foreach ($pasos as $titulo => [$comando, $opciones]) {
            $this->components->info($titulo.' ('.$comando.')');

            if ($this->call($comando, $opciones) !== self::SUCCESS) {
                $this->components->error('Ha fallado «'.$comando.'». No se sigue con lo demás.');

                return self::FAILURE;
            }
        }

        $this->components->info('Todo al día.');

        return self::SUCCESS;
    }
}
