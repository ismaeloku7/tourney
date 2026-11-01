<?php

namespace App\Actions\Competicion;

use App\Competicion\Errores\FaseConResultados;
use App\Competicion\Errores\OperacionNoPermitida;
use App\Competicion\GeneradorRoundRobin;
use App\Enums\EstadoPartido;
use App\Models\Fase;
use App\Models\RegistroDeCambio;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Crea los partidos de cada grupo (todos contra todos o número fijo, ida
 * y vuelta si toca) en estado pendiente, con su jornada, y las filas de
 * clasificación a cero. Devuelve cuántos partidos ha creado.
 */
final class GenerarPartidos
{
    public function __construct(
        private readonly GeneradorRoundRobin $generador = new GeneradorRoundRobin,
        private readonly RecalcularClasificacion $recalcular = new RecalcularClasificacion,
    ) {}

    public function handle(Fase $fase, User $usuario): int
    {
        if (! $fase->tipo->esDeGrupos()) {
            throw new OperacionNoPermitida('Los partidos de un cuadro los crea el cierre de la fase anterior.');
        }

        if ($fase->grupos()->doesntExist()) {
            throw new OperacionNoPermitida('Antes de generar los partidos hay que sortear los grupos.');
        }

        if ($fase->tieneResultados()) {
            throw new FaseConResultados;
        }

        $configuracion = $fase->configuracionDeGrupos();

        return DB::transaction(function () use ($fase, $usuario, $configuracion): int {
            $fase->partidos()->forceDelete();
            $creados = 0;

            foreach ($fase->grupos()->with('participantes')->get() as $grupo) {
                foreach ($this->generador->generar($grupo->aDto(), $configuracion->partidosPorParticipante, $configuracion->idaYVuelta) as $dto) {
                    $fase->partidos()->create([
                        'grupo_id' => $grupo->id,
                        'jornada' => $dto->jornada,
                        'local_id' => $dto->local,
                        'visitante_id' => $dto->visitante,
                        'estado' => EstadoPartido::Pendiente,
                    ]);
                    $creados++;
                }

                $this->recalcular->handle($grupo);
            }

            RegistroDeCambio::anotar('generar_partidos', $fase, $usuario, null, ['partidos' => $creados]);

            return $creados;
        });
    }
}
