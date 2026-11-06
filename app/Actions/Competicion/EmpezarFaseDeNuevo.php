<?php

namespace App\Actions\Competicion;

use App\Competicion\Errores\OperacionNoPermitida;
use App\Enums\EstadoFase;
use App\Enums\TipoDeFase;
use App\Models\Fase;
use App\Models\Partido;
use App\Models\RegistroDeCambio;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Deja una fase como recién creada, para volver a sortearla: fuera sus
 * grupos, su clasificación y sus partidos, con las horas que tenían en el
 * calendario. Si es de grupos y detrás va un cuadro sin jugar, el cuadro
 * también se vacía (se volverá a llenar al cerrar los grupos). Solo toca
 * esa categoría: las demás y sus horarios se quedan como están.
 */
final class EmpezarFaseDeNuevo
{
    /** @return int Partidos borrados. */
    public function handle(Fase $fase, User $usuario, bool $aunqueHayaResultados = false): int
    {
        if ($fase->estado === EstadoFase::Cerrada) {
            throw new OperacionNoPermitida('La fase está cerrada: ya no se empieza de nuevo.');
        }

        $cuadro = $fase->tipo->esDeGrupos() ? $fase->siguiente() : null;
        if ($cuadro !== null && ($cuadro->tipo !== TipoDeFase::Eliminatoria || $cuadro->tieneResultados())) {
            $cuadro = null;
        }

        if (! $aunqueHayaResultados && $fase->tieneResultados()) {
            throw new OperacionNoPermitida('La fase ya tiene resultados: si empiezas de nuevo se pierden. Marca que quieres hacerlo igualmente.');
        }

        return DB::transaction(function () use ($fase, $cuadro, $usuario): int {
            $partidos = $fase->partidos()->count() + ($cuadro?->partidos()->count() ?? 0);

            Partido::borrarConSusHoras($fase->partidos());
            $fase->clasificaciones()->delete();
            $fase->grupos()->delete();

            $configuracion = $fase->configuracion;
            unset($configuracion['grupos_manuales'], $configuracion['repartidos']);
            $fase->update(['estado' => EstadoFase::Pendiente, 'configuracion' => $configuracion]);

            if ($cuadro !== null) {
                Partido::borrarConSusHoras($cuadro->partidos());
                $cuadro->update(['estado' => EstadoFase::Pendiente]);
            }

            RegistroDeCambio::anotar('empezar_fase_de_nuevo', $fase, $usuario, null, ['partidos_borrados' => $partidos, 'cuadro' => $cuadro?->id]);

            return $partidos;
        });
    }
}
