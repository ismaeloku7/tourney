<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** La mesa: sin sesión del equipo (o con la cuenta desactivada), a su página de acceso. */
class ExigirEquipo
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = Auth::guard('web')->user();

        if (! $usuario instanceof User || ! $usuario->activo) {
            return redirect()->route('mesa.acceso');
        }

        return $next($request);
    }
}
