<?php

namespace App\Competicion\Errores;

use DomainException;

/**
 * Lo que el motor lanza ante una entrada imposible. Lo que es una decisión
 * del organizador (grupos desiguales, conflictos de horario) no es un
 * error: vuelve como aviso en el DTO de salida.
 */
class ErrorDeCompeticion extends DomainException {}
