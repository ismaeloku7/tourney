<?php

namespace App\Competicion\Datos;

use DateTimeImmutable;

/** Lo que el planificador tiene que respetar. */
final readonly class RestriccionesDePlanificacion
{
    /**
     * @param  int  $descansoMinimoMinutos  Entre el final de un partido y el comienzo del juego del siguiente de un jugador (0: puede enlazar).
     * @param  array<int, list<Intervalo>>  $noDisponibilidades  Jugador → intervalos en los que no puede.
     * @param  int  $calentamientoMinutos  Minutos de pista para calentar antes de cada partido. Quien viene de jugar no calienta: puede acabar su partido mientras el rival calienta.
     * @param  DateTimeImmutable|null  $noAntesDe  Nada empieza antes (al replanificar con el torneo en marcha).
     * @param  list<int>  $pistasExteriores  Las pistas al aire libre: los de grupos procuran jugar alguna vez en ellas; los de cuadro, nunca.
     */
    public function __construct(
        public int $descansoMinimoMinutos = 0,
        public array $noDisponibilidades = [],
        public int $calentamientoMinutos = 10,
        public ?DateTimeImmutable $noAntesDe = null,
        public array $pistasExteriores = [],
    ) {}
}
