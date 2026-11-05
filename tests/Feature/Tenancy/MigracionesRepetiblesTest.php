<?php

use App\Models\FormatoPartido;
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

test('todos los organizadores tienen el formato «Dos sets + super tie-break», sin duplicarlo', function () {
    $this->seed(PlanesSeeder::class);
    usarOrganizador(Organizador::factory()->create());
    $migracion = require database_path('migrations/tenant/2026_11_10_000000_add_formato_dos_sets_super_tie_break.php');
    $cuantos = fn (string $nombre): int => FormatoPartido::query()->where('nombre', $nombre)->count();

    // Uno nuevo nace con él.
    $migracion->up();
    expect($cuantos('Dos sets + super tie-break'))->toBe(1);

    // Uno antiguo, con el nombre de antes: se renombra.
    FormatoPartido::query()->where('nombre', 'Dos sets + super tie-break')->update(['nombre' => 'Al mejor de 3 con super tie-break']);
    $migracion->up();
    expect($cuantos('Dos sets + super tie-break'))->toBe(1)->and($cuantos('Al mejor de 3 con super tie-break'))->toBe(0);

    // Uno que lo borró: se crea de nuevo, con dos sets a 6 y el super tie-break a 10.
    FormatoPartido::query()->where('nombre', 'Dos sets + super tie-break')->delete();
    $migracion->up();
    $formato = FormatoPartido::query()->where('nombre', 'Dos sets + super tie-break')->sole();
    expect($formato->sets)->toBe(3)
        ->and($formato->juegos_por_set)->toBe(6)
        ->and($formato->super_tie_break_final)->toBeTrue()
        ->and($formato->super_tie_break_puntos)->toBe(10);
});
