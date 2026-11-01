<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Rutas que solo existen en el dominio de la plataforma (alta, legales del SaaS): en el host de un organizador, 404. */
class SoloPlataforma
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(tenancy()->initialized, 404);

        return $next($request);
    }
}
