<?php

namespace App\Actions\Competicion;

use App\Competicion\AvanceDeCuadro;
use App\Models\Partido;

/**
 * Interno: tras cerrar un partido de cuadro, lleva a su ganador (y a su
 * perdedor, si hay tercer puesto o consolación) a los huecos que los
 * esperan y persiste lo que cambie.
 */
final class AvanzarCuadro
{
    public function __construct(
        private readonly AvanceDeCuadro $avance = new AvanceDeCuadro,
        private readonly Cuadros $cuadros = new Cuadros,
    ) {}

    public function handle(Partido $cerrado): void
    {
        $fase = $cerrado->fase;

        if (! $fase->tipo->esDeCuadro()) {
            return;
        }

        $cuadro = $this->cuadros->cargar($fase);
        $this->cuadros->guardar($this->avance->avanzar($cuadro, $cerrado->aDto()));
    }
}
