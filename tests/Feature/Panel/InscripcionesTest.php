<?php

use App\Actions\Inscripciones\InscribirPareja;
use App\Enums\EstadoInscripcion;
use App\Enums\OrigenInscripcion;
use App\Enums\Rol;
use App\Filament\Resources\Categorias\Pages\EditCategoria;
use App\Filament\Resources\Categorias\RelationManagers\InscripcionesRelationManager;
use App\Models\Categoria;
use App\Models\Inscripcion;
use App\Models\Jugador;
use App\Models\Organizador;
use App\Models\User;
use Database\Seeders\PlanesSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PlanesSeeder::class);
    usarOrganizador(Organizador::factory()->create());
    Filament::setCurrentPanel('panel');

    $this->propietario = User::factory()->rol(Rol::Propietario)->create();
    $this->categoria = Categoria::factory()->create(['cupo' => 2, 'nombre' => 'Masculina 4ª']);
    $this->actingAs($this->propietario);
});

function componenteDeInscripciones(Categoria $categoria): Testable
{
    return Livewire::test(InscripcionesRelationManager::class, ['ownerRecord' => $categoria, 'pageClass' => EditCategoria::class]);
}

test('la organización inscribe a una pareja con jugadores de la base', function () {
    $garcia = Jugador::factory()->create(['apellidos' => 'García']);
    $lopez = Jugador::factory()->create(['apellidos' => 'López']);

    componenteDeInscripciones($this->categoria)
        ->assertSee('0 confirmadas de 2')
        ->callAction(TestAction::make('inscribir')->table(), ['jugador_1_id' => $garcia->id, 'jugador_2_id' => $lopez->id, 'confirmada' => true, 'notas' => 'Pagan el viernes'])
        ->assertHasNoActionErrors()
        ->assertNotified('Pareja inscrita');

    $inscripcion = Inscripcion::query()->sole();

    expect($inscripcion->estado)->toBe(EstadoInscripcion::Confirmada)
        ->and($inscripcion->origen)->toBe(OrigenInscripcion::Manual)
        ->and($inscripcion->registrada_por)->toBe($this->propietario->id)
        ->and($inscripcion->notas)->toBe('Pagan el viernes')
        ->and($inscripcion->participante->nombre)->toBe('García / López');

    componenteDeInscripciones($this->categoria)
        ->assertCanSeeTableRecords([$inscripcion])
        ->assertSee('García / López')
        ->assertSee('1 confirmadas de 2');
});

test('la misma pareja no se inscribe dos veces y el aviso lo dice', function () {
    $garcia = Jugador::factory()->create(['nombre' => 'Ana', 'apellidos' => 'García']);
    $lopez = Jugador::factory()->create();
    $perez = Jugador::factory()->create();
    app(InscribirPareja::class)->handle($this->categoria, $garcia, $lopez, OrigenInscripcion::Manual);

    componenteDeInscripciones($this->categoria)
        ->callAction(TestAction::make('inscribir')->table(), ['jugador_1_id' => $garcia->id, 'jugador_2_id' => $perez->id, 'confirmada' => true])
        ->assertNotified('No se ha podido inscribir');

    expect(Inscripcion::query()->count())->toBe(1);
});

test('desde la tabla se confirma, se da de baja, se anota el pago y se fija la cabeza de serie', function () {
    $parejas = collect(range(1, 3))->map(function () {
        [$a, $b] = Jugador::factory()->count(2)->create()->all();

        return app(InscribirPareja::class)->handle($this->categoria, $a, $b, OrigenInscripcion::Manual);
    });
    $enEspera = $parejas[2];
    expect($enEspera->estado)->toBe(EstadoInscripcion::ListaDeEspera);

    $componente = componenteDeInscripciones($this->categoria)->assertSee('1 en espera');

    // Al cupo: confirmar a la que espera no puede ser.
    $componente->callAction(TestAction::make('confirmar')->table($enEspera))->assertNotified('No se ha podido cambiar la inscripción');

    // Baja de la primera, y entonces sí. (Componente nuevo tras una acción
    // rechazada: su diálogo se queda abierto en el anterior.)
    $componente = componenteDeInscripciones($this->categoria);
    $componente->callAction(TestAction::make('baja')->table($parejas[0]))->assertHasNoActionErrors();
    $componente->callAction(TestAction::make('confirmar')->table($enEspera))->assertHasNoActionErrors();

    expect($parejas[0]->refresh()->estado)->toBe(EstadoInscripcion::Baja)
        ->and($enEspera->refresh()->estado)->toBe(EstadoInscripcion::Confirmada);

    $componente->callAction(TestAction::make('pago')->table($enEspera), ['pagado' => true, 'pago_notas' => 'Bizum'])->assertHasNoActionErrors();
    $componente->callAction(TestAction::make('cabezaDeSerie')->table($enEspera), ['numero' => 1])->assertHasNoActionErrors();
    $componente->callAction(TestAction::make('cabezaDeSerie')->table($parejas[1]), ['numero' => 1])->assertNotified('No se ha podido fijar');

    expect($enEspera->refresh()->pago_marcado_en)->not->toBeNull()
        ->and($enEspera->pago_notas)->toBe('Bizum')
        ->and($enEspera->participante->cabeza_de_serie)->toBe(1)
        ->and($parejas[1]->participante->refresh()->cabeza_de_serie)->toBeNull();

    // Las acciones que no tocan no se ofrecen.
    componenteDeInscripciones($this->categoria)->assertActionHidden(TestAction::make('confirmar')->table($enEspera))
        ->assertActionHidden(TestAction::make('baja')->table($parejas[0]))
        ->assertActionVisible(TestAction::make('readmitir')->table($parejas[0]));
});
