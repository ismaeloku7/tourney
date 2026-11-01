<?php

namespace App\Actions\Competicion;

use App\Enums\EstadoFase;
use App\Models\Partido;

/**
 * Lo que pasa cada vez que un partido queda cerrado o deja de estarlo:
 * la clasificación de su grupo se recalcula, el cuadro avanza y la fase
 * pasa a «en juego».
 */
final class ConsolidarPartidoCerrado
{
    public function __construct(
        private readonly RecalcularClasificacion $recalcular = new RecalcularClasificacion,
        private readonly AvanzarCuadro $avanzar = new AvanzarCuadro,
    ) {}

    public function handle(Partido $partido): void
    {
        $partido->refresh();

        if ($partido->grupo_id !== null) {
            $this->recalcular->handle($partido->grupo);
        }

        if ($partido->cerrado()) {
            $this->avanzar->handle($partido);
        }

        $fase = $partido->fase;

        if ($fase->estado === EstadoFase::Preparada) {
            $fase->estado = EstadoFase::EnJuego;
            $fase->save();
        }
    }
}
