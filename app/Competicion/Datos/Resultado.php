<?php

namespace App\Competicion\Datos;

use App\Enums\ResultadoEspecial;

/**
 * Lo que se registra de un partido: sus sets, o un resultado especial
 * (walkover, retirada, no presentado). `ganaLocal` es obligatorio con
 * especial (salvo no presentado) y en los partidos por tiempo.
 */
final readonly class Resultado
{
    /** @param  list<Set>  $sets */
    public function __construct(
        public array $sets = [],
        public ?ResultadoEspecial $especial = null,
        public ?bool $ganaLocal = null,
    ) {}

    public function setsGanadosPorLocal(): int
    {
        return count(array_filter($this->sets, fn (Set $set): bool => $set->ganaLocal()));
    }

    public function setsGanadosPorVisitante(): int
    {
        return count(array_filter($this->sets, fn (Set $set): bool => ! $set->ganaLocal() && $set->juegosLocal !== $set->juegosVisitante));
    }

    public function juegosDeLocal(): int
    {
        return array_sum(array_map(fn (Set $set): int => $set->esSuperTieBreak ? 0 : $set->juegosLocal, $this->sets));
    }

    public function juegosDeVisitante(): int
    {
        return array_sum(array_map(fn (Set $set): int => $set->esSuperTieBreak ? 0 : $set->juegosVisitante, $this->sets));
    }
}
