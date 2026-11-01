<?php

namespace App\Competicion\Datos;

/** Si un resultado vale para un formato, sus errores y quién gana. */
final readonly class ValidacionDeResultado
{
    /** @param  list<string>  $errores */
    public function __construct(
        public bool $valido,
        public array $errores = [],
        public ?bool $ganaLocal = null,
        public int $setsLocal = 0,
        public int $setsVisitante = 0,
    ) {}
}
