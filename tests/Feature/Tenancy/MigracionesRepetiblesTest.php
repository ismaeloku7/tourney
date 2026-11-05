<?php

use App\Models\Organizador;
use Database\Seeders\PlanesSeeder;
use Illuminate\Support\Facades\Schema;

/**
 * Las migraciones de los horarios en directo y de las duraciones se
 * pueden volver a lanzar sin romper nada: en MySQL los cambios de tablas
 * no se deshacen si un despliegue se corta a medias, y al reintentar se
 * encuentran parte hecha.
 */
test('volver a lanzar las migraciones de horarios en directo y duraciones no rompe nada', function () {
    $this->seed(PlanesSeeder::class);
    usarOrganizador(Organizador::factory()->create());

    foreach (['2026_11_07_000000_add_horarios_en_directo', '2026_11_08_000000_add_duracion_real_to_partidos'] as $nombre) {
        $migracion = require database_path("migrations/tenant/{$nombre}.php");
        $migracion->up();
        $migracion->up();
    }

    expect(Schema::hasColumn('franjas', 'inicio_previsto'))->toBeTrue()
        ->and(Schema::hasIndex('franjas', 'franjas_pista_id_inicio_index'))->toBeTrue()
        ->and(Schema::hasIndex('franjas', 'franjas_pista_id_inicio_unique'))->toBeFalse()
        ->and(Schema::hasColumn('partidos', 'aviso_calentar_en'))->toBeTrue()
        ->and(Schema::hasColumn('partidos', 'minutos_jugados'))->toBeTrue();
});
