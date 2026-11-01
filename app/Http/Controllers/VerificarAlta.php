<?php

namespace App\Http\Controllers;

use App\Actions\Plataforma\VerificarOrganizador;
use App\Models\Organizador;
use Illuminate\Contracts\View\View;

/**
 * El enlace del correo de alta. Si vale, enseña la página de «ya está»
 * con la dirección del panel; si no, la de enlace caducado.
 */
class VerificarAlta
{
    public function __invoke(string $organizador, string $token, VerificarOrganizador $verificar): View
    {
        $encontrado = Organizador::query()->find($organizador);
        $activado = $encontrado === null ? null : $verificar->handle($encontrado, $token);

        return view('alta-verificada', [
            'organizador' => $activado,
            'nombre' => $encontrado?->nombre,
        ]);
    }
}
