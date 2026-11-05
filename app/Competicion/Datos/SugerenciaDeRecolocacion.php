<?php

namespace App\Competicion\Datos;

/**
 * Pasar un partido de una pista que va con retraso a otra que va bien,
 * con margen para avisar: a qué pista y hora, y cuánto se gana (minutos
 * de retraso que se ahorran las parejas y minutos que se adelanta el
 * final).
 */
final readonly class SugerenciaDeRecolocacion
{
    public function __construct(
        public int $partido,
        public int $desdePista,
        public int $haciaPista,
        public int $inicio,
        public float $orden,
        public int $retrasoDeLaPista,
        public int $ganaRetraso,
        public int $ganaFinal,
    ) {}

    public function clave(): string
    {
        return $this->partido.'-'.$this->haciaPista.'-'.$this->inicio;
    }
}
