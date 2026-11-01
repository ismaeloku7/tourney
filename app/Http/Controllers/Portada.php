<?php

namespace App\Http\Controllers;

use App\Models\Organizador;
use App\Publico\TorneosPublicos;
use Illuminate\Contracts\View\View;

/**
 * La raíz de cada host: en el dominio del SaaS, la landing de la
 * plataforma; en el host de un organizador, su portada con sus torneos
 * abiertos, en juego, próximos y recientes.
 */
class Portada
{
    public function __invoke(): View
    {
        $organizador = tenant();

        return $organizador instanceof Organizador
            ? view('organizador.portada', [
                'organizador' => $organizador,
                'abiertos' => TorneosPublicos::abiertos(),
                'enJuego' => TorneosPublicos::enJuego(),
                'proximos' => TorneosPublicos::proximos(),
                'recientes' => TorneosPublicos::recientes(),
            ])
            : view('portada');
    }
}
