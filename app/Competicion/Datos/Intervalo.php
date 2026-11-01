<?php

namespace App\Competicion\Datos;

use DateTimeImmutable;

/** Un tramo de tiempo cerrado por la izquierda y abierto por la derecha. */
final readonly class Intervalo
{
    public function __construct(
        public DateTimeImmutable $desde,
        public DateTimeImmutable $hasta,
    ) {}

    public function solapa(Intervalo $otro): bool
    {
        return $this->desde < $otro->hasta && $otro->desde < $this->hasta;
    }

    public function minutos(): int
    {
        return intdiv($this->hasta->getTimestamp() - $this->desde->getTimestamp(), 60);
    }
}
