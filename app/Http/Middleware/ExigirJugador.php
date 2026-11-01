<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** El portal del jugador: sin sesión de jugador, a la página de acceso, con vuelta aquí. */
class ExigirJugador
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('jugador')->check()) {
            return redirect()->route('jugador.acceso', ['destino' => '/'.ltrim($request->path(), '/')]);
        }

        return $next($request);
    }
}
