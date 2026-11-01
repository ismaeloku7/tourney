<?php

use App\Models\Organizador;
use App\Tenancy\OrganizadorActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Las pruebas de `Feature` arrancan Laravel con la base central en memoria;
| cada organizador que crean tiene su base (un archivo SQLite) que se borra
| al acabar (TestCase). Las de `Unit` (el motor) no tocan Laravel.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Funciones
|--------------------------------------------------------------------------
*/

/**
 * Ejecuta `$accion` con `$organizador` como organizador actual.
 *
 * @template T
 *
 * @param  callable(Organizador): T  $accion
 * @return T
 */
function comoOrganizador(Organizador $organizador, callable $accion): mixed
{
    return app(OrganizadorActual::class)->ejecutarComo($organizador, $accion);
}

/**
 * Deja `$organizador` como organizador actual durante el resto de la prueba.
 *
 * Solo para pruebas de componentes (Livewire, Filament) que no pasan por
 * el middleware de host. En la aplicación, el organizador lo fija el
 * middleware u `OrganizadorActual::ejecutarComo()`.
 */
function usarOrganizador(Organizador $organizador): void
{
    tenancy()->initialize($organizador);
}
