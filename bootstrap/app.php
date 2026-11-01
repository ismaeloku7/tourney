<?php

use App\Http\Middleware\ExigirOrganizador;
use App\Http\Middleware\ResolverOrganizadorPorHost;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Primero el organizador: un host desconocido no llega a abrir sesión.
        $middleware->web(prepend: [ResolverOrganizadorPorHost::class]);

        // Sin organizador, 404 antes de buscar el torneo en una base que no hay.
        $middleware->prependToPriorityList(SubstituteBindings::class, ExigirOrganizador::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
