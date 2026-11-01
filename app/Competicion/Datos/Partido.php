<?php

namespace App\Competicion\Datos;

use App\Enums\EstadoPartido;

/**
 * Un partido tal y como lo ve el motor: de grupo (con jornada) o de
 * cuadro (con ronda, posición y los orígenes de los huecos vacíos). Es
 * inmutable: `con()` devuelve una copia con cambios.
 */
final readonly class Partido
{
    public function __construct(
        public ?int $id = null,
        public ?int $grupo = null,
        public ?int $jornada = null,
        public ?int $ronda = null,
        public ?int $posicion = null,
        public bool $esTercerPuesto = false,
        public ?int $local = null,
        public ?int $visitante = null,
        public ?Origen $origenLocal = null,
        public ?Origen $origenVisitante = null,
        public EstadoPartido $estado = EstadoPartido::Pendiente,
        public ?int $ganador = null,
        public ?Resultado $resultado = null,
    ) {}

    /** Una copia con los campos indicados cambiados (argumentos con nombre). */
    public function con(mixed ...$cambios): self
    {
        /** @var array<string, mixed> $valores */
        $valores = array_merge(get_object_vars($this), $cambios);

        return new self(...$valores);
    }

    public function conGanador(int $ganador, ?Resultado $resultado = null, EstadoPartido $estado = EstadoPartido::Jugado): self
    {
        return $this->con(ganador: $ganador, resultado: $resultado ?? $this->resultado, estado: $estado);
    }

    /** Sin resultado ni ganador, como recién creado. */
    public function vacio(): self
    {
        return $this->con(ganador: null, resultado: null, estado: EstadoPartido::Pendiente);
    }

    public function cerrado(): bool
    {
        return $this->estado->cerrado();
    }

    public function perdedor(): ?int
    {
        if ($this->ganador === null || $this->local === null || $this->visitante === null) {
            return null;
        }

        return $this->ganador === $this->local ? $this->visitante : $this->local;
    }

    public function juega(int $participante): bool
    {
        return $this->local === $participante || $this->visitante === $participante;
    }

    /** El rival de un participante en este partido, si está. */
    public function rivalDe(int $participante): ?int
    {
        return match ($participante) {
            $this->local => $this->visitante,
            $this->visitante => $this->local,
            default => null,
        };
    }

    /** Un hueco libre con origen: todavía no se sabe quién lo ocupa. */
    public function tieneHuecoPendiente(): bool
    {
        return ($this->local === null && $this->origenLocal !== null) || ($this->visitante === null && $this->origenVisitante !== null);
    }
}
