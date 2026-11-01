<?php

namespace App\Console\Commands;

use App\Actions\Plataforma\DestruirOrganizador as Accion;
use App\Models\Organizador;
use Illuminate\Console\Command;

/**
 * Borra un organizador y su base. Sin vuelta atrás: exige --confirmar.
 */
class DestruirOrganizador extends Command
{
    protected $signature = 'organizadores:destruir {slug : El organizador} {--confirmar : Sin esto no hace nada}';

    protected $description = 'Borra un organizador, sus dominios y su base de datos';

    public function handle(Accion $accion): int
    {
        $organizador = Organizador::query()->find((string) $this->argument('slug'));

        if ($organizador === null) {
            $this->components->error('No hay ningún organizador con ese slug.');

            return self::FAILURE;
        }

        if (! $this->option('confirmar')) {
            $this->components->warn("Se borraría «{$organizador->nombre}» con su base {$organizador->nombreDeBase()}. Repita con --confirmar.");

            return self::FAILURE;
        }

        $accion->handle($organizador);

        $this->components->info("Organizador «{$organizador->nombre}» borrado con su base.");

        return self::SUCCESS;
    }
}
