<?php

namespace App\Competicion\Datos;

/** Un partido pendiente de hora y pista, con lo que el planificador necesita saber de él. */
final readonly class PartidoAPlanificar
{
    /**
     * @param  list<int>  $jugadores  Los jugadores de los dos lados (para no solapar personas).
     * @param  list<int>  $dependeDe  Partidos que tienen que jugarse antes.
     */
    public function __construct(
        public int $id,
        public int $categoria,
        public int $prioridadDeCategoria,
        public ?int $ronda,
        public ?int $jornada,
        public ?int $local,
        public ?int $visitante,
        public array $jugadores,
        public int $duracionMinutos,
        public array $dependeDe = [],
    ) {}
}
