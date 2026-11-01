<?php

namespace App\Competicion\Errores;

/** Con `n` participantes no se puede dar a cada uno exactamente `k` partidos. */
class NumeroDePartidosNoAlcanzable extends ErrorDeCompeticion
{
    public function __construct(int $participantes, int $partidos)
    {
        parent::__construct("Con {$participantes} participantes no se pueden repartir {$partidos} partidos por participante sin repetir cruces ni dejar a nadie con un partido de más.");
    }
}
