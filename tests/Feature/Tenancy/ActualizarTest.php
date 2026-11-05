<?php

use App\Models\Organizador;
use Database\Seeders\PlanesSeeder;

/** El comando de después de cada despliegue: migra la central y cada organizador. */
test('actualizar migra la central y los organizadores, con o sin --force', function () {
    $this->seed(PlanesSeeder::class);
    Organizador::factory()->create();

    $this->artisan('actualizar')->expectsOutputToContain('Todo al día.')->assertSuccessful();
    $this->artisan('actualizar', ['--force' => true])->expectsOutputToContain('Todo al día.')->assertSuccessful();
});
