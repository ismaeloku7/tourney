<?php

namespace App\Competicion\Datos;

/**
 * El resultado de repartir: los grupos, lo que conviene que vea el
 * organizador y la semilla con la que se hizo (para poder repetirlo).
 */
final readonly class RepartoDeGrupos
{
    /**
     * @param  list<Grupo>  $grupos
     * @param  list<string>  $avisos
     */
    public function __construct(
        public array $grupos,
        public array $avisos = [],
        public ?int $semilla = null,
    ) {}
}
