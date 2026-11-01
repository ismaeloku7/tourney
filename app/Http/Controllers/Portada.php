<?php

namespace App\Http\Controllers;

use App\Models\Organizador;
use Illuminate\Contracts\View\View;

/**
 * La raíz de cada host: en el dominio del SaaS, la landing de la
 * plataforma; en el host de un organizador, su portada (la de verdad,
 * con sus torneos, llega en la fase 2).
 */
class Portada
{
    public function __invoke(): View
    {
        $organizador = tenant();

        return $organizador instanceof Organizador
            ? view('organizador.portada', ['organizador' => $organizador])
            : view('portada');
    }
}
