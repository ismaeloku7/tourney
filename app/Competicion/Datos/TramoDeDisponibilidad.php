<?php

namespace App\Competicion\Datos;

/** Un día y un intervalo de horas en que una pista está disponible. */
final readonly class TramoDeDisponibilidad
{
    public function __construct(
        public string $fecha,
        public string $desde,
        public string $hasta,
    ) {}

    /** @return array{fecha: string, desde: string, hasta: string} */
    public function toArray(): array
    {
        return ['fecha' => $this->fecha, 'desde' => $this->desde, 'hasta' => $this->hasta];
    }
}
