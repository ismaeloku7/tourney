<?php

namespace App\Competicion\Errores;

/** Un participante aparece dos veces donde solo puede estar una. */
class ParticipanteDuplicado extends ErrorDeCompeticion
{
    public function __construct(int $participante)
    {
        parent::__construct("El participante {$participante} aparece más de una vez.");
    }
}
