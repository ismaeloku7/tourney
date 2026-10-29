<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * La transacción de RefreshDatabase va en la base central, también
     * cuando al acabar la prueba haya un organizador activo y la conexión
     * por defecto sea la suya.
     *
     * @var list<string>
     */
    protected array $connectionsToTransact = [];

    protected function setUp(): void
    {
        $this->connectionsToTransact = [(string) env('DB_CONNECTION', 'sqlite')];

        parent::setUp();
    }
}
