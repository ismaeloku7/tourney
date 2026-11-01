<?php

namespace App\Competicion\Errores;

/** Resortear o regenerar una fase que ya tiene partidos jugados exige confirmación explícita. */
class FaseConResultados extends ErrorDeCompeticion
{
    public function __construct()
    {
        parent::__construct('La fase ya tiene resultados. Para repetir el sorteo hay que anularlos, confirmándolo expresamente.');
    }
}
