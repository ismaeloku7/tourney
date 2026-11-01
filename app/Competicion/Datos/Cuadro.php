<?php

namespace App\Competicion\Datos;

/**
 * Un cuadro eliminatorio: su tamaño (potencia de 2), sus rondas y todos
 * sus partidos (la primera ronda con participantes o byes; las demás con
 * orígenes). Inmutable: `conPartidos()` devuelve una copia.
 */
final readonly class Cuadro
{
    /**
     * @param  list<Partido>  $partidos
     * @param  list<string>  $avisos
     */
    public function __construct(
        public int $tamano,
        public int $rondas,
        public array $partidos,
        public array $avisos = [],
    ) {}

    /** @return list<Partido> */
    public function partidosDeRonda(int $ronda): array
    {
        return array_values(array_filter($this->partidos, fn (Partido $p): bool => $p->ronda === $ronda && ! $p->esTercerPuesto));
    }

    public function partido(int $id): ?Partido
    {
        foreach ($this->partidos as $partido) {
            if ($partido->id === $id) {
                return $partido;
            }
        }

        return null;
    }

    public function final(): ?Partido
    {
        return $this->partidosDeRonda($this->rondas)[0] ?? null;
    }

    public function tercerPuesto(): ?Partido
    {
        foreach ($this->partidos as $partido) {
            if ($partido->esTercerPuesto) {
                return $partido;
            }
        }

        return null;
    }

    /** @param  list<Partido>  $partidos */
    public function conPartidos(array $partidos): self
    {
        return new self($this->tamano, $this->rondas, $partidos, $this->avisos);
    }
}
