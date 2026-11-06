<?php

use App\Models\FormatoPartido;
use App\Models\Organizador;
use App\Models\Pista;
use App\Models\Torneo;
use Database\Seeders\Organizador\BaseDelOrganizadorSeeder;
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

test('las duraciones de serie se ajustan solo si nadie las ha cambiado a mano', function () {
    $this->seed(PlanesSeeder::class);
    usarOrganizador(Organizador::factory()->create());
    (new BaseDelOrganizadorSeeder)->run();
    $minutos = fn (string $nombre): int => (int) FormatoPartido::query()->where('nombre', $nombre)->value('duracion_estimada');

    // Uno nuevo nace con las nuevas.
    expect($minutos('Un set a 6 con tie-break'))->toBe(40)
        ->and($minutos('Un set a 9'))->toBe(50)
        ->and($minutos('Dos sets + super tie-break'))->toBe(75)
        ->and($minutos('Al mejor de 3 sets a 6'))->toBe(90);

    // Uno antiguo: las de serie de antes cambian; la puesta a mano, no.
    FormatoPartido::query()->where('nombre', 'Un set a 9')->update(['duracion_estimada' => 60]);
    FormatoPartido::query()->where('nombre', 'Dos sets + super tie-break')->update(['duracion_estimada' => 60]);
    FormatoPartido::query()->where('nombre', 'Al mejor de 3 sets a 6')->update(['duracion_estimada' => 100]);

    $migracion = require database_path('migrations/tenant/2026_11_11_000000_ajustar_duraciones_de_formatos.php');
    $migracion->up();
    $migracion->up();

    expect($minutos('Un set a 9'))->toBe(50)
        ->and($minutos('Dos sets + super tie-break'))->toBe(75)
        ->and($minutos('Al mejor de 3 sets a 6'))->toBe(100);
});

test('las pistas pueden ser exteriores y el descanso de serie de 30 minutos pasa a 0', function () {
    $this->seed(PlanesSeeder::class);
    usarOrganizador(Organizador::factory()->create());
    $sinTocar = Torneo::factory()->create(['descanso_minimo_minutos' => 30]);
    $aMano = Torneo::factory()->create(['descanso_minimo_minutos' => 15]);

    $migracion = require database_path('migrations/tenant/2026_11_12_000000_add_exterior_a_pistas_y_sin_descanso.php');
    $migracion->up();
    $migracion->up();

    expect(Schema::hasColumn('pistas', 'exterior'))->toBeTrue()
        ->and(Pista::factory()->create(['exterior' => true])->refresh()->exterior)->toBeTrue()
        ->and($sinTocar->refresh()->descanso_minimo_minutos)->toBe(0)
        ->and($aMano->refresh()->descanso_minimo_minutos)->toBe(15);
});
