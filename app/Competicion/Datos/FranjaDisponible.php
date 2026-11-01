<?php

namespace App\Competicion\Datos;

use DateTimeImmutable;

/** Un hueco libre de una pista. */
final readonly class FranjaDisponible
{
    public function __construct(
        public int $id,
        public int $pista,
        public DateTimeImmutable $inicio,
        public DateTimeImmutable $fin,
    ) {}

    public function intervalo(): Intervalo
    {
        return new Intervalo($this->inicio, $this->fin);
    }

    public function duracionMinutos(): int
    {
        return $this->intervalo()->minutos();
    }
}
