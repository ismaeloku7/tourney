<?php

namespace App\Competicion\Datos;

use App\Enums\CriterioDeDesempate;

/** Una fila de la tabla de un grupo. */
final readonly class LineaDeClasificacion
{
    public function __construct(
        public int $participante,
        public int $posicion = 0,
        public int $puntos = 0,
        public int $pj = 0,
        public int $pg = 0,
        public int $pp = 0,
        public int $setsFavor = 0,
        public int $setsContra = 0,
        public int $juegosFavor = 0,
        public int $juegosContra = 0,
        public ?CriterioDeDesempate $criterioAplicado = null,
        public ?int $sorteo = null,
    ) {}

    public function con(mixed ...$cambios): self
    {
        /** @var array<string, mixed> $valores */
        $valores = array_merge(get_object_vars($this), $cambios);

        return new self(...$valores);
    }

    public function diferenciaDeSets(): int
    {
        return $this->setsFavor - $this->setsContra;
    }

    public function diferenciaDeJuegos(): int
    {
        return $this->juegosFavor - $this->juegosContra;
    }
}
