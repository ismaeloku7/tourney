<?php

namespace App\Http\Controllers;

use App\Models\Organizador;
use App\Publico\TorneosPublicos;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * La raíz de cada host: en el dominio del SaaS, la landing de la
 * plataforma; en el host de un organizador, su portada con sus torneos
 * abiertos, en juego, próximos y recientes, salvo para un jugador con
 * sesión, que va a su portal.
 */
class Portada
{
    public function __invoke(): View|RedirectResponse
    {
        $organizador = tenant();

        // Un jugador con sesión entra directo a su portal: su inicio son sus torneos.
        if ($organizador instanceof Organizador && Auth::guard('jugador')->check()) {
            return redirect()->route('jugador.portal');
        }

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
