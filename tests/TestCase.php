<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Events\DatabaseCreated;

abstract class TestCase extends BaseTestCase
{
    /** @var list<TenantWithDatabase> Organizadores cuya base ha creado la prueba. */
    private array $basesDeOrganizador = [];

    /**
     * La transacción de RefreshDatabase va en la base central, aunque al
     * acabar la prueba haya un organizador activo y la conexión por
     * defecto sea la suya. Es la conexión por defecto del proyecto.
     *
     * @var list<string>
     */
    protected array $connectionsToTransact = [];

    protected function setUp(): void
    {
        $this->connectionsToTransact = [(string) env('DB_CONNECTION', 'sqlite')];

        parent::setUp();

        // Cada organizador que crea una prueba tiene su base (un archivo
        // SQLite o una base MySQL). La transacción de RefreshDatabase no la
        // deshace: se borra al acabar.
        $this->app['events']->listen(DatabaseCreated::class, function (DatabaseCreated $evento): void {
            $this->basesDeOrganizador[] = $evento->tenant;
        });

        $this->beforeApplicationDestroyed(function (): void {
            tenancy()->end();

            foreach ($this->basesDeOrganizador as $organizador) {
                $gestor = $organizador->database()->manager();

                // La prueba puede haberla borrado ya (destruir el organizador).
                if ($gestor->databaseExists($organizador->database()->getName())) {
                    $gestor->deleteDatabase($organizador);
                }
            }

            $this->basesDeOrganizador = [];
        });
    }
}
