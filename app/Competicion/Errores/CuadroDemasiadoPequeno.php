<?php

namespace App\Competicion\Errores;

/** El tamaño de cuadro fijado no da para los clasificados. */
class CuadroDemasiadoPequeno extends ErrorDeCompeticion
{
    public function __construct(int $tamano, int $clasificados)
    {
        parent::__construct("Un cuadro de {$tamano} no tiene sitio para {$clasificados} clasificados.");
    }
}
