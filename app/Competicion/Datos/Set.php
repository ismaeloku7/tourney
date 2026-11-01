<?php

namespace App\Competicion\Datos;

/**
 * Un set: juegos de cada lado y, si lo hubo, los puntos del tie-break. En
 * un super tie-break los «juegos» son los puntos del super tie-break.
 */
final readonly class Set
{
    public function __construct(
        public int $juegosLocal,
        public int $juegosVisitante,
        public ?int $tieBreakLocal = null,
        public ?int $tieBreakVisitante = null,
        public bool $esSuperTieBreak = false,
    ) {}

    public function ganaLocal(): bool
    {
        return $this->juegosLocal > $this->juegosVisitante;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'juegos_local' => $this->juegosLocal,
            'juegos_visitante' => $this->juegosVisitante,
            'tie_break_local' => $this->tieBreakLocal,
            'tie_break_visitante' => $this->tieBreakVisitante,
            'es_super_tie_break' => $this->esSuperTieBreak,
        ];
    }
}
