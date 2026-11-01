<?php

namespace App\Competicion\Datos;

final readonly class Asignacion
{
    public function __construct(
        public int $partido,
        public FranjaDisponible $franja,
    ) {}
}
