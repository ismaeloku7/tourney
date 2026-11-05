<?php

namespace App\Competicion\Datos;

/**
 * Quien compite, tal y como lo ve el motor: un id opaco, un nombre para
 * los avisos, su cabeza de serie si la tiene, sus jugadores (para que el
 * planificador sepa quién juega en dos categorías) y su nivel medio (para
 * el sorteo por niveles).
 */
final readonly class Participante
{
    /** @param  list<int>  $jugadores */
    public function __construct(
        public int $id,
        public string $nombre,
        public ?int $cabezaDeSerie = null,
        public array $jugadores = [],
        public ?float $nivel = null,
    ) {}
}
