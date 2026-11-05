<?php

namespace App\Competicion\Datos;

use DateTimeImmutable;

/**
 * Lo que sale del planificador: los partidos colocados, los que no caben
 * con su motivo y cómo de aprovechadas quedan las pistas (entre el primer
 * partido y el último, dentro de su disponibilidad).
 */
final readonly class PlanDePistas
{
    /**
     * @param  list<PartidoColocado>  $colocados
     * @param  list<Conflicto>  $conflictos
     */
    public function __construct(
        public array $colocados,
        public array $conflictos = [],
        public ?DateTimeImmutable $fin = null,
        public int $minutosMuertos = 0,
        public int $ocupacion = 0,
    ) {}

    public function colocadoDe(int $partido): ?PartidoColocado
    {
        foreach ($this->colocados as $colocado) {
            if ($colocado->partido === $partido) {
                return $colocado;
            }
        }

        return null;
    }
}
