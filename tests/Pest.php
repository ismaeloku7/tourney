<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Las pruebas de `Feature` arrancan Laravel con la base central en memoria.
| Las de `Unit` (el motor de competición) no tocan Laravel.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Funciones
|--------------------------------------------------------------------------
|
| Los ayudantes para trabajar como un organizador (`comoOrganizador`,
| `usarOrganizador`) llegan con la base central y stancl/tenancy.
|
*/
