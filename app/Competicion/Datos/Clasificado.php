<?php

namespace App\Competicion\Datos;

/** Quien pasa a la siguiente fase, con de dónde viene y su orden global (1 = el mejor). */
final readonly class Clasificado
{
    public function __construct(
        public int $participante,
        public ?int $grupo,
        public int $posicionEnGrupo,
        public string $origen,
        public int $orden,
    ) {}
}
