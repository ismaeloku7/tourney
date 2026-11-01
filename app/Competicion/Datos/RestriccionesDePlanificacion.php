<?php

namespace App\Competicion\Datos;

/** Lo que el planificador tiene que respetar. */
final readonly class RestriccionesDePlanificacion
{
    /**
     * @param  array<int, list<Intervalo>>  $noDisponibilidades  Jugador → intervalos en los que no puede.
     * @param  array<int, FranjaDisponible>  $partidosYaFijados  Partido → franja que ya tiene.
     */
    public function __construct(
        public int $descansoMinimoMinutos = 30,
        public array $noDisponibilidades = [],
        public array $partidosYaFijados = [],
    ) {}
}
