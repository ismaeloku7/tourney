<?php

namespace App\Competicion\Datos;

use DateTimeImmutable;

/** Dónde y cuándo va un partido: su pista desde el calentamiento hasta el final estimado. */
final readonly class PartidoColocado
{
    public function __construct(
        public int $partido,
        public int $pista,
        public DateTimeImmutable $inicio,
        public DateTimeImmutable $fin,
    ) {}
}
