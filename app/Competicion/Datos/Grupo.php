<?php

namespace App\Competicion\Datos;

/** Un grupo con sus participantes en orden de sorteo. */
final readonly class Grupo
{
    /** @param  list<int>  $participantes */
    public function __construct(
        public string $nombre,
        public int $orden,
        public array $participantes,
    ) {}

    public function tamano(): int
    {
        return count($this->participantes);
    }
}
