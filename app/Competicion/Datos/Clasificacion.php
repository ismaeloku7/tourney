<?php

namespace App\Competicion\Datos;

/** La tabla ordenada de un grupo. `completa` si todos sus partidos están cerrados. */
final readonly class Clasificacion
{
    /** @param  list<LineaDeClasificacion>  $lineas */
    public function __construct(
        public ?int $grupo,
        public array $lineas,
        public bool $completa,
    ) {}

    public function linea(int $participante): ?LineaDeClasificacion
    {
        foreach ($this->lineas as $linea) {
            if ($linea->participante === $participante) {
                return $linea;
            }
        }

        return null;
    }

    public function enPosicion(int $posicion): ?LineaDeClasificacion
    {
        foreach ($this->lineas as $linea) {
            if ($linea->posicion === $posicion) {
                return $linea;
            }
        }

        return null;
    }

    /** @return list<int> */
    public function participantesEnOrden(): array
    {
        return array_map(fn (LineaDeClasificacion $linea): int => $linea->participante, $this->lineas);
    }
}
