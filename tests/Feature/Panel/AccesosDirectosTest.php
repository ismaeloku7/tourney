<?php

use App\Enums\EstadoInscripcion;
use App\Enums\Rol;
use App\Filament\Resources\Parejas\Pages\ListParejas;
use App\Filament\Resources\Torneos\Pages\EditTorneo;
use App\Filament\Resources\Torneos\RelationManagers\ParejasRelationManager;
use App\Models\AccesoDirecto;
use App\Models\Categoria;
use App\Models\Inscripcion;
use App\Models\Jugador;
use App\Models\Organizador;
use App\Models\Participante;
use App\Models\Torneo;
use App\Models\User;
use Database\Seeders\PlanesSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

/** Cada pareja puede tener un enlace para entrar en su portal sin correo ni contraseña. */
beforeEach(function () {
    $this->seed(PlanesSeeder::class);
    usarOrganizador(Organizador::factory()->create(['id' => 'norte']));
    Filament::setCurrentPanel('panel');

    $this->torneo = Torneo::factory()->create();
    $categoria = Categoria::factory()->create(['torneo_id' => $this->torneo->id]);
    $this->ana = Jugador::factory()->create(['nombre' => 'Ana', 'apellidos' => 'García']);
    $this->eva = Jugador::factory()->create(['nombre' => 'Eva', 'apellidos' => 'López']);
    $this->inscripcion = Inscripcion::factory()->create([
        'participante_id' => Participante::factory()->conJugadores($this->ana, $this->eva)->create(['categoria_id' => $categoria->id])->id,
    ]);
    $this->otra = Inscripcion::factory()->create([
        'participante_id' => Participante::factory()->conJugadores(Jugador::factory()->create(), Jugador::factory()->create())->create(['categoria_id' => $categoria->id])->id,
    ]);
    $this->deBaja = Inscripcion::factory()->create([
        'estado' => EstadoInscripcion::Baja,
        'participante_id' => Participante::factory()->conJugadores(Jugador::factory()->create())->create(['categoria_id' => $categoria->id])->id,
    ]);
    $this->actingAs(User::factory()->rol(Rol::Propietario)->create());
});

test('en cada pareja se crea su enlace, se ve para copiarlo y con él se entra sin contraseña', function () {
    Livewire::test(ListParejas::class)
        ->assertActionDoesNotExist(TestAction::make('niveles')->table($this->inscripcion))
        ->assertActionVisible(TestAction::make('crearAcceso')->table($this->inscripcion))
        ->assertActionHidden(TestAction::make('verAcceso')->table($this->inscripcion))
        ->callAction(TestAction::make('crearAcceso')->table($this->inscripcion))
        ->assertNotified('Enlace creado');

    $acceso = AccesoDirecto::query()->where('inscripcion_id', $this->inscripcion->id)->sole();
    expect($acceso->jugador_id)->toBe($this->ana->id)
        ->and($acceso->token)->toHaveLength(48)
        ->and($acceso->token_hash)->toBe(hash('sha256', $acceso->token));

    Livewire::test(ListParejas::class)
        ->assertActionHidden(TestAction::make('crearAcceso')->table($this->inscripcion))
        ->assertActionVisible(TestAction::make('verAcceso')->table($this->inscripcion))
        ->mountAction(TestAction::make('verAcceso')->table($this->inscripcion))
        ->assertActionMounted(TestAction::make('verAcceso')->table($this->inscripcion));

    // El enlace no caduca: sirve hoy y dentro de un año, y lleva a su torneo.
    $this->travel(365)->days();
    $this->get("http://norte.saas.test/acceso/{$acceso->token}")
        ->assertRedirectContains("/jugador/torneos/{$this->inscripcion->id}");
    $this->assertAuthenticatedAs($this->ana, 'jugador');
    expect($acceso->refresh()->ultimo_uso_en)->not->toBeNull();
});

test('arriba se crean los de todas las parejas (no las de baja) y se borran todos', function () {
    $componente = Livewire::test(ParejasRelationManager::class, ['ownerRecord' => $this->torneo, 'pageClass' => EditTorneo::class])
        ->callAction(TestAction::make('crearAccesos')->table())
        ->assertNotified('2 enlaces creados');

    expect(AccesoDirecto::query()->pluck('inscripcion_id')->sort()->values()->all())->toBe([$this->inscripcion->id, $this->otra->id]);
    $token = AccesoDirecto::query()->where('inscripcion_id', $this->inscripcion->id)->sole()->token;

    // Repetir no duplica ni cambia los que ya hay.
    $componente->callAction(TestAction::make('crearAccesos')->table())->assertNotified('0 enlaces creados');
    expect(AccesoDirecto::query()->count())->toBe(2)
        ->and(AccesoDirecto::query()->where('inscripcion_id', $this->inscripcion->id)->sole()->token)->toBe($token);

    $componente->callAction(TestAction::make('borrarAccesos')->table())->assertNotified('2 enlaces borrados');
    expect(AccesoDirecto::query()->count())->toBe(0);

    // Borrado, el enlace ya no vale.
    $this->get("http://norte.saas.test/acceso/{$token}")->assertOk()->assertSee('Este enlace ya no vale');
    $this->assertGuest('jugador');
});

test('el enlace de una pareja que se da de baja deja de valer', function () {
    Livewire::test(ListParejas::class)->callAction(TestAction::make('crearAcceso')->table($this->inscripcion));
    $token = AccesoDirecto::query()->sole()->token;
    $this->inscripcion->update(['estado' => EstadoInscripcion::Baja]);

    $this->get("http://norte.saas.test/acceso/{$token}")->assertSee('Este enlace ya no vale');
    $this->assertGuest('jugador');
    $this->get('http://norte.saas.test/acceso/no-existe')->assertSee('Este enlace ya no vale');
});
