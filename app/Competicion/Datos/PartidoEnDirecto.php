<?php

namespace App\Competicion\Datos;

/**
 * Un partido con pista y hora visto en directo, con los instantes como
 * marcas de tiempo (segundos): dónde está, cuánto ocupa la pista
 * (calentamiento incluido), quién juega y de qué partidos depende, si ya
 * acabó (y cuándo) y a qué hora estaba previsto al planificar.
 */
final readonly class PartidoEnDirecto
{
    /**
     * @param  list<int>  $jugadores
     * @param  list<int>  $dependeDe  Partidos de los que salen sus parejas (aún por saber).
     * @param  float|null  $orden  Dónde va en la cola de su pista; sin él, por su hora.
     * @param  int|null  $noAntesDe  No empieza antes (para avisar con tiempo al moverlo).
     * @param  bool  $empezado  Ya se está jugando: `inicio` es cuando empezó y `minutos`, lo que le queda de juego.
     */
    public function __construct(
        public int $id,
        public int $pista,
        public int $inicio,
        public int $minutos,
        public array $jugadores = [],
        public array $dependeDe = [],
        public bool $cerrado = false,
        public ?int $finReal = null,
        public ?int $previsto = null,
        public ?float $orden = null,
        public ?int $noAntesDe = null,
        public bool $empezado = false,
    ) {}

    public function fin(): int
    {
        return $this->finReal ?? $this->inicio + $this->minutos * 60;
    }

    public function con(int $pista, int $inicio, ?float $orden = null, ?int $noAntesDe = null): self
    {
        return new self($this->id, $pista, $inicio, $this->minutos, $this->jugadores, $this->dependeDe, $this->cerrado, $this->finReal, $this->previsto, $orden, $noAntesDe, $this->empezado);
    }
}
