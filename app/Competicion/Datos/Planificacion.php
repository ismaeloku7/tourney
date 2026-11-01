<?php

namespace App\Competicion\Datos;

final readonly class Planificacion
{
    /**
     * @param  list<Asignacion>  $asignaciones
     * @param  list<Conflicto>  $conflictos
     */
    public function __construct(
        public array $asignaciones,
        public array $conflictos = [],
    ) {}

    public function franjaDe(int $partido): ?FranjaDisponible
    {
        foreach ($this->asignaciones as $asignacion) {
            if ($asignacion->partido === $partido) {
                return $asignacion->franja;
            }
        }

        return null;
    }
}
