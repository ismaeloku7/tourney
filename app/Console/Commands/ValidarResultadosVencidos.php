<?php

namespace App\Console\Commands;

use App\Actions\Competicion\ValidarResultado;
use App\Competicion\Errores\ErrorDeCompeticion;
use App\Enums\EstadoOrganizador;
use App\Enums\EstadoPartido;
use App\Models\Organizador;
use App\Models\Partido;
use Illuminate\Console\Command;

/**
 * Los resultados que dejó un jugador y el rival no validó a tiempo cuentan
 * solos al vencer el plazo del torneo. Recorre todos los organizadores
 * activos; se programa cada minuto.
 */
class ValidarResultadosVencidos extends Command
{
    protected $signature = 'resultados:validar-vencidos';

    protected $description = 'Da por buenos los resultados de jugadores cuyo plazo de validación ha vencido';

    public function handle(ValidarResultado $validar): int
    {
        $total = 0;

        foreach (Organizador::query()->where('estado', EstadoOrganizador::Activo->value)->get() as $organizador) {
            assert($organizador instanceof Organizador);
            $total += (int) $organizador->run(function () use ($validar): int {
                $validados = 0;

                foreach (Partido::query()->where('estado', EstadoPartido::PendienteValidacion->value)->whereNotNull('validacion_vence_en')->where('validacion_vence_en', '<=', now())->get() as $partido) {
                    try {
                        $validar->handle($partido, null);
                        $validados++;
                    } catch (ErrorDeCompeticion $e) {
                        report($e);
                    }
                }

                return $validados;
            });
        }

        $this->info("Resultados validados: {$total}.");

        return self::SUCCESS;
    }
}
