<?php

namespace App\Competicion\Datos;

use DateTimeImmutable;

/** Lo que el planificador tiene que respetar. */
final readonly class RestriccionesDePlanificacion
{
    /**
     * @param  array<int, list<Intervalo>>  $noDisponibilidades  Jugador → intervalos en los que no puede.
     * @param  int  $calentamientoMinutos  Minutos de pista para calentar antes de cada partido.
     * @param  DateTimeImmutable|null  $noAntesDe  Nada empieza antes (al replanificar con el torneo en marcha).
     */
    public function __construct(
        public int $descansoMinimoMinutos = 30,
        public array $noDisponibilidades = [],
        public int $calentamientoMinutos = 10,
        public ?DateTimeImmutable $noAntesDe = null,
    ) {}
}
