<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rutas que solo existen en el host de un organizador (su panel, la
 * inscripción, el portal del jugador): en el dominio del SaaS, 404.
 */
class ExigirOrganizador
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(tenancy()->initialized, 404);

        return $next($request);
    }
}
