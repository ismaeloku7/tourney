<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rutas que solo existen en el dominio del SaaS (la plataforma, el alta):
 * en el host de un organizador, 404.
 */
class ExigirCentral
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(tenancy()->initialized, 404);

        return $next($request);
    }
}
