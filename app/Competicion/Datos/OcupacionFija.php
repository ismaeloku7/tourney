<?php

namespace App\Competicion\Datos;

/**
 * Un tiempo de pista que el planificador no toca: un partido ya
 * programado, en juego o jugado (que además ocupa a sus jugadores), o un
 * bloqueo de la pista (sin partido ni jugadores).
 */
final readonly class OcupacionFija
{
    /** @param  list<int>  $jugadores */
    public function __construct(
        public int $pista,
        public Intervalo $intervalo,
        public ?int $partido = null,
        public array $jugadores = [],
    ) {}
}
