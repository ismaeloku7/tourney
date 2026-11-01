<?php

use App\Actions\Competicion\GenerarFranjas;
use App\Actions\Competicion\GenerarPartidos;
use App\Actions\Competicion\PlanificarHorarios;
use App\Actions\Competicion\RegistrarResultado;
use App\Actions\Competicion\SortearGrupos;
use App\Competicion\Datos\Resultado;
use App\Competicion\Datos\Set;
use App\Enums\EstadoPartido;
use App\Enums\EstadoTorneo;
use App\Enums\ResultadosPorJugadores;
use App\Enums\Rol;
use App\Filament\Resources\Torneos\Pages\EditTorneo;
use App\Filament\Resources\Torneos\RelationManagers\ArbitrosRelationManager;
use App\Livewire\Mesa\AccesoDeMesa;
use App\Livewire\Mesa\MesaDeArbitro;
use App\Models\Categoria;
use App\Models\Fase;
use App\Models\FormatoPartido;
use App\Models\Inscripcion;
use App\Models\Organizador;
use App\Models\Participante;
use App\Models\Partido;
use App\Models\Pista;
use App\Models\Torneo;
use App\Models\TorneoArbitro;
use App\Models\User;
use Database\Seeders\PlanesSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

/**
 * Un torneo en juego hoy con 8 parejas en dos grupos de 4, dos pistas y
 * horarios planificados.
 */
beforeEach(function () {
    $this->seed(PlanesSeeder::class);
    usarOrganizador(Organizador::factory()->create(['id' => 'norte']));
    FormatoPartido::factory()->predeterminado()->create(['duracion_estimada' => 60]);
    $this->propietario = User::factory()->rol(Rol::Propietario)->create();
    $this->arbitro = User::factory()->rol(Rol::Arbitro)->create(['email' => 'arbitro@example.com', 'password' => 'clave-de-arbitro-12']);

    $this->torneo = Torneo::factory()->create(['estado' => EstadoTorneo::Sorteo, 'fecha_inicio' => today()->toDateString(), 'fecha_fin' => today()->toDateString(), 'descanso_minimo_minutos' => 0]);
    [$this->central, $this->dos] = Pista::factory()->count(2)->create()->all();
    foreach ([$this->central, $this->dos] as $orden => $pista) {
        $this->torneo->torneoPistas()->create(['pista_id' => $pista->id, 'orden' => $orden + 1, 'disponibilidad' => [
            ['fecha' => today()->toDateString(), 'desde' => '08:00', 'hasta' => '23:00'],
        ]]);
    }
    $categoria = Categoria::factory()->create(['torneo_id' => $this->torneo->id]);
    foreach (range(1, 8) as $i) {
        Inscripcion::factory()->create(['participante_id' => Participante::factory()->create(['categoria_id' => $categoria->id])->id]);
    }
    $fase = Fase::factory()->grupos(['semilla' => 2])->create(['categoria_id' => $categoria->id]);
    app(SortearGrupos::class)->handle($fase, $this->propietario);
    app(GenerarPartidos::class)->handle($fase, $this->propietario);
    app(GenerarFranjas::class)->handle($this->torneo);
    app(PlanificarHorarios::class)->handle($this->torneo, $this->propietario);
    $this->torneo->update(['estado' => EstadoTorneo::EnJuego, 'horarios_publicados_en' => now()]);

    TorneoArbitro::query()->create(['torneo_id' => $this->torneo->id, 'user_id' => $this->arbitro->id, 'pista_id' => $this->central->id]);
});

test('el árbitro entra en la mesa y la mesa sin sesión lleva al acceso', function () {
    $this->get('http://norte.saas.test/mesa')->assertRedirect(route('mesa.acceso'));

    Livewire::test(AccesoDeMesa::class)
        ->set(['email' => 'arbitro@example.com', 'clave' => 'mala'])
        ->call('entrar')
        ->assertHasErrors('email');

    Livewire::test(AccesoDeMesa::class)
        ->set(['email' => 'arbitro@example.com', 'clave' => 'clave-de-arbitro-12'])
        ->call('entrar')
        ->assertRedirect(route('mesa'));
    $this->assertAuthenticatedAs($this->arbitro, 'web');

    $this->get('http://norte.saas.test/mesa')->assertOk()->assertSee('Mesa');
});

test('el árbitro ve solo los partidos de su pista y anota sus resultados, también un walkover', function () {
    $this->actingAs($this->arbitro);

    $mesa = Livewire::test(MesaDeArbitro::class);
    $ids = array_column($mesa->viewData('partidos'), 'id');

    expect($ids)->not->toBeEmpty()
        ->and(Partido::query()->whereIn('id', $ids)->pluck('pista_id')->unique()->all())->toBe([$this->central->id]);

    $suyo = Partido::query()->findOrFail($ids[0]);
    // El formato por defecto no tiene súper tie-break: un 10-8 en el tercer set no vale.
    $mesa->call('abrirResultado', $suyo->id)
        ->set('sets.0.juegos_local', 6)->set('sets.0.juegos_visitante', 2)
        ->set('sets.1.juegos_local', 3)->set('sets.1.juegos_visitante', 6)
        ->set('sets.2.juegos_local', 10)->set('sets.2.juegos_visitante', 8)
        ->call('guardarResultado')
        ->assertHasErrors('resultado');
    $mesa->set('sets.2.juegos_local', 6)->set('sets.2.juegos_visitante', 4)
        ->call('guardarResultado')
        ->assertHasNoErrors()
        ->assertSet('aviso', 'Resultado guardado.');
    expect($suyo->refresh()->estado)->toBe(EstadoPartido::Jugado)->and($suyo->ganador_id)->toBe($suyo->local_id);

    $otro = Partido::query()->findOrFail($ids[1]);
    Livewire::test(MesaDeArbitro::class)
        ->call('abrirResultado', $otro->id)
        ->set('especial', 'walkover')->set('ganaLocal', '0')
        ->call('guardarResultado')
        ->assertHasNoErrors();
    expect($otro->refresh()->estado)->toBe(EstadoPartido::Walkover)->and($otro->ganador_id)->toBe($otro->visitante_id);

    // Un partido de la otra pista no es suyo.
    $ajeno = Partido::query()->where('pista_id', $this->dos->id)->whereIn('estado', ['programado'])->firstOrFail();
    Livewire::test(MesaDeArbitro::class)->call('abrirResultado', $ajeno->id)->call('guardarResultado')->assertForbidden();
});

test('la mesa valida lo que dejó un jugador y el plazo vencido lo valida solo', function () {
    $this->torneo->update(['resultados_por_jugadores' => ResultadosPorJugadores::ConValidacion]);
    [$uno, $dos] = Partido::query()->where('pista_id', $this->central->id)->orderBy('id')->limit(2)->get()->all();

    foreach ([$uno, $dos] as $partido) {
        app(RegistrarResultado::class)->handle($partido, new Resultado([new Set(6, 1), new Set(6, 1)]), $partido->local->jugadores->first());
    }
    expect($uno->refresh()->estado)->toBe(EstadoPartido::PendienteValidacion);

    $this->actingAs($this->arbitro);
    Livewire::test(MesaDeArbitro::class)->call('validar', $uno->id)->assertHasNoErrors();
    expect($uno->refresh()->estado)->toBe(EstadoPartido::Jugado);

    $this->travel(31)->minutes();
    $this->artisan('resultados:validar-vencidos')->expectsOutputToContain('Resultados validados: 1')->assertSuccessful();
    expect($dos->refresh()->estado)->toBe(EstadoPartido::Jugado)->and($dos->validado_por)->toBeNull();
});

test('el propietario asigna árbitros a las pistas del torneo desde el panel', function () {
    Filament::setCurrentPanel('panel');
    $this->actingAs($this->propietario);
    $otro = User::factory()->rol(Rol::Arbitro)->create();

    Livewire::test(ArbitrosRelationManager::class, ['ownerRecord' => $this->torneo, 'pageClass' => EditTorneo::class])
        ->callAction(TestAction::make('create')->table(), ['user_id' => $otro->id, 'pista_id' => $this->dos->id])
        ->assertHasNoActionErrors();

    expect(TorneoArbitro::query()->where('user_id', $otro->id)->sole()->pista_id)->toBe($this->dos->id);
});
