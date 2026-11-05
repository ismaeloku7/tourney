<?php

namespace App\Competicion\Datos;

/**
 * El cruce de primera ronda que tendría un participante si la fase de
 * grupos acabase como va: su rival (o bye) y de dónde vendría cada uno.
 */
final readonly class CruceProyectado
{
    public function __construct(
        public int $participante,
        public string $origen,
        public ?int $rival,
        public ?string $origenDelRival,
    ) {}

    public function esBye(): bool
    {
        return $this->rival === null;
    }

    /** «2º B», «mejor 3º (C)» o «Bye». */
    public function etiqueta(): string
    {
        return $this->origenDelRival ?? 'Bye';
    }
}
