<?php

namespace App\Competicion\Datos;

/** El resultado de repartir: los grupos y lo que conviene que vea el organizador. */
final readonly class RepartoDeGrupos
{
    /**
     * @param  list<Grupo>  $grupos
     * @param  list<string>  $avisos
     */
    public function __construct(
        public array $grupos,
        public array $avisos = [],
    ) {}
}
