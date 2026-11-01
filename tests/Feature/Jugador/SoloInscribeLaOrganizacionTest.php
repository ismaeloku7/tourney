<?php

use App\Enums\EstadoTorneo;
use App\Enums\Rol;
use App\Filament\Resources\Categorias\Pages\EditCategoria;
use App\Filament\Resources\Categorias\RelationManagers\InscripcionesRelationManager;
use App\Filament\Resources\Torneos\Pages\EditTorneo;
use App\Livewire\Publico\AccesoJugador;
use App\Livewire\Publico\InscripcionEnTorneo;
use App\Livewire\Publico\PortalDelJugador;
use App\Mail\EnlaceDeAcceso;
use App\Models\Categoria;
use App\Models\Inscripcion;
use App\Models\Jugador;
use App\Models\Organizador;
use App\Models\Torneo;
use App\Models\User;
use Database\Seeders\PlanesSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

/**
 * Un torneo con la inscripción abierta pero sin inscripción online: solo
 * inscribe la organización, y solo entran los jugadores que ella añade.
 */
beforeEach(function () {
    $this->seed(PlanesSeeder::class);
    Mail::fake();
    usarOrganizador(Organizador::factory()->create(['id' => 'norte']));
    Filament::setCurrentPanel('panel');

    $this->propietario = User::factory()->rol(Rol::Propietario)->create();
    $this->torneo = Torneo::factory()->conInscripcionAbierta()->soloInscribeLaOrganizacion()->create(['slug' => 'open-cerrado', 'contacto' => '600 000 000']);
    $this->categoria = Categoria::factory()->create(['torneo_id' => $this->torneo->id]);
});

test('la web no ofrece inscribirse y explica que inscribe la organización', function () {
    $this->get('http://norte.saas.test/torneos/open-cerrado')
        ->assertOk()
        ->assertDontSee('Inscribirnos')
        ->assertSee('En este torneo inscribe la organización')
        ->assertSee('600 000 000');

    Livewire::test(InscripcionEnTorneo::class, ['torneo' => $this->torneo])->assertSee('En este torneo inscribe la organización');
});

test('nadie puede crearse una cuenta ni inscribirse por su cuenta', function () {
    Livewire::test(InscripcionEnTorneo::class, ['torneo' => $this->torneo])
        ->set(['email' => 'intruso@example.com', 'nombre' => 'Intruso', 'apellidos' => 'Pérez', 'privacidad' => true])
        ->call('identificarme')
        ->assertHasErrors('inscripcion');

    expect(Jugador::query()->where('email', 'intruso@example.com')->exists())->toBeFalse();
    $this->assertGuest('jugador');

    // Ni con una cuenta de otro torneo.
    $this->actingAs(Jugador::factory()->create(), 'jugador');
    Livewire::test(InscripcionEnTorneo::class, ['torneo' => $this->torneo])
        ->set(['categoria_id' => $this->categoria->id, 'companero_email' => 'otro@example.com', 'companero_nombre' => 'Otro', 'companero_apellidos' => 'Más'])
        ->call('inscribirme')
        ->assertHasErrors('inscripcion');

    expect(Inscripcion::query()->count())->toBe(0);

    // Y un correo que no es de ningún jugador no recibe enlace de acceso.
    auth('jugador')->logout();
    Livewire::test(AccesoJugador::class)->set('email', 'intruso@example.com')->call('enviar')->assertSet('enviado', true);
    Mail::assertNotSent(EnlaceDeAcceso::class);
});

test('la organización inscribe a una pareja con jugadores nuevos y esos jugadores sí entran en su portal', function () {
    $this->actingAs($this->propietario);

    $componente = Livewire::test(InscripcionesRelationManager::class, ['ownerRecord' => $this->categoria, 'pageClass' => EditCategoria::class]);

    $garcia = Jugador::factory()->create(['nombre' => 'Ana', 'apellidos' => 'García', 'email' => 'ana@example.com']);
    $lopez = Jugador::factory()->create(['nombre' => 'Eva', 'apellidos' => 'López', 'email' => 'eva@example.com']);

    $componente->callAction(TestAction::make('inscribir')->table(), ['jugador_1_id' => $garcia->id, 'jugador_2_id' => $lopez->id, 'confirmada' => true])
        ->assertHasNoActionErrors();
    expect(Inscripcion::query()->count())->toBe(1);

    // Ana pide su enlace, entra y ve su inscripción.
    auth('web')->logout();
    Livewire::test(AccesoJugador::class)->set('email', 'ana@example.com')->call('enviar');
    $enlace = null;
    Mail::assertSent(EnlaceDeAcceso::class, function (EnlaceDeAcceso $correo) use (&$enlace): bool {
        $enlace = $correo->enlace;

        return $correo->hasTo('ana@example.com');
    });
    preg_match('#/jugador/entrar/([A-Za-z0-9]{64})#', (string) $enlace, $m);

    $this->get('http://norte.saas.test/jugador/entrar/'.$m[1])->assertRedirect(route('jugador.portal'));
    $this->assertAuthenticatedAs($garcia, 'jugador');

    Livewire::test(PortalDelJugador::class)->assertSee($this->torneo->nombre)->assertSee('García / López');
});

test('el organizador activa o desactiva la inscripción online desde la ficha del torneo', function () {
    $this->actingAs($this->propietario);

    Livewire::test(EditTorneo::class, ['record' => $this->torneo->getRouteKey()])
        ->fillForm(['inscripcion_online' => true])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->torneo->refresh()->inscripcion_online)->toBeTrue()
        ->and($this->torneo->admiteInscripcionOnline())->toBeTrue();

    $this->torneo->update(['estado' => EstadoTorneo::InscripcionCerrada]);
    expect($this->torneo->admiteInscripcionOnline())->toBeFalse();
});
