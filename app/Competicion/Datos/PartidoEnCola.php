<?php

namespace App\Competicion\Datos;

use DateTimeImmutable;

/**
 * Un partido en la cola de una pista, para estimar a qué hora empieza:
 * quién juega, cuánto dura su formato y, si ya ha pasado, cuándo empezó
 * y acabó de verdad.
 */
final readonly class PartidoEnCola
{
    public function __construct(
        public int $id,
        public int $orden,
        public ?int $local,
        public ?int $visitante,
        public int $duracionMinutos,
        public ?DateTimeImmutable $empezadoEn = null,
        public ?DateTimeImmutable $terminadoEn = null,
        public bool $cerrado = false,
    ) {}
}
