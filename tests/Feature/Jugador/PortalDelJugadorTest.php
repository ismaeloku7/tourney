<?php

use App\Actions\Competicion\GenerarPartidos;
use App\Actions\Competicion\SortearGrupos;
use App\Actions\Jugadores\CambiarNivel;
use App\Competicion\Errores\OperacionNoPermitida;
use App\Enums\EstadoInscripcion;
use App\Enums\EstadoPartido;
use App\Enums\EstadoTorneo;
use App\Enums\ResultadosPorJugadores;
use App\Enums\Rol;
use App\Livewire\Publico\PortalDelJugador;
use App\Models\Categoria;
use App\Models\Disponibilidad;
use App\Models\Fase;
use App\Models\FormatoPartido;
use App\Models\Inscripcion;
use App\Models\Jugador;
use App\Models\Organizador;
use App\Models\Participante;
use App\Models\Partido;
use App\Models\RegistroDeCambio;
use App\Models\Torneo;
use App\Models\User;
use Database\Seeders\PlanesSeeder;
use Livewire\Livewire;

/**
 * Un torneo con 4 parejas en un grupo; Ana y Eva son una de ellas.
 */
beforeEach(function () {
    $this->seed(PlanesSeeder::class);
    usarOrganizador(Organizador::factory()->create(['id' => 'norte']));
    FormatoPartido::factory()->predeterminado()->create();

    $this->torneo = Torneo::factory()->conInscripcionAbierta()->create(['nombre' => 'Open de Primavera', 'resultados_por_jugadores' => ResultadosPorJugadores::ConValidacion, 'minutos_para_validar' => 30]);
    $this->categoria = Categoria::factory()->create(['torneo_id' => $this->torneo->id, 'nombre' => 'Mixta B']);
    $this->ana = Jugador::factory()->create(['nombre' => 'Ana', 'apellidos' => 'García']);
    $this->eva = Jugador::factory()->create(['nombre' => 'Eva', 'apellidos' => 'López']);

    $this->nuestra = Participante::factory()->conJugadores($this->ana, $this->eva)->create(['categoria_id' => $this->categoria->id, 'nombre' => 'García / López']);
    $this->inscripcion = Inscripcion::factory()->create(['participante_id' => $this->nuestra->id]);
    foreach (range(1, 3) as $i) {
        Inscripcion::factory()->create(['participante_id' => Participante::factory()->create(['categoria_id' => $this->categoria->id])->id]);
    }

    $this->actingAs($this->ana, 'jugador');
});

function sortearElTorneo(Torneo $torneo, Categoria $categoria): Fase
{
    $usuario = User::factory()->rol(Rol::Propietario)->create();
    $fase = Fase::factory()->grupos(['semilla' => 5])->create(['categoria_id' => $categoria->id]);
    app(SortearGrupos::class)->handle($fase, $usuario);
    app(GenerarPartidos::class)->handle($fase, $usuario);
    $torneo->update(['estado' => EstadoTorneo::EnJuego]);

    return $fase;
}

test('el portal enseña sus inscripciones y deja darse de baja solo con la inscripción abierta', function () {
    $this->get('http://norte.saas.test/jugador')->assertOk()->assertSee('Hola, Ana')->assertSee('Open de Primavera')->assertSee('García / López');

    Livewire::test(PortalDelJugador::class)
        ->call('darmeDeBaja', $this->inscripcion->id)
        ->assertSet('aviso', 'Te has dado de baja.');
    expect($this->inscripcion->refresh()->estado)->toBe(EstadoInscripcion::Baja);

    // La de otra pareja no es suya.
    $ajena = Inscripcion::query()->whereKeyNot($this->inscripcion->id)->firstOrFail();
    Livewire::test(PortalDelJugador::class)->call('darmeDeBaja', $ajena->id)->assertHasErrors('portal');

    // Con la inscripción cerrada, ya no.
    $this->inscripcion->update(['estado' => EstadoInscripcion::Confirmada]);
    $this->torneo->update(['estado' => EstadoTorneo::InscripcionCerrada]);
    Livewire::test(PortalDelJugador::class)->call('darmeDeBaja', $this->inscripcion->id)->assertHasErrors('portal');
    expect($this->inscripcion->refresh()->estado)->toBe(EstadoInscripcion::Confirmada);
});

test('un jugador anota el resultado y solo la pareja rival lo valida', function () {
    $fase = sortearElTorneo($this->torneo, $this->categoria);
    $partido = $fase->partidos()->where(fn ($q) => $q->where('local_id', $this->nuestra->id)->orWhere('visitante_id', $this->nuestra->id))->orderBy('id')->firstOrFail();
    $nosotrosLocal = $partido->local_id === $this->nuestra->id;

    Livewire::test(PortalDelJugador::class)
        ->assertSee('Anotar resultado')
        ->call('abrirResultado', $partido->id)
        ->set('sets.0.juegos_local', 7)->set('sets.0.juegos_visitante', 3)
        ->set('sets.1.juegos_local', 6)->set('sets.1.juegos_visitante', 4)
        ->call('guardarResultado')
        ->assertHasErrors('resultado');

    Livewire::test(PortalDelJugador::class)
        ->call('abrirResultado', $partido->id)
        ->set('sets.0.juegos_local', 6)->set('sets.0.juegos_visitante', 3)
        ->set('sets.1.juegos_local', 6)->set('sets.1.juegos_visitante', 4)
        ->call('guardarResultado')
        ->assertHasNoErrors()
        ->assertSet('aviso', 'Resultado anotado. Falta que lo valide la pareja rival.');

    expect($partido->refresh()->estado)->toBe(EstadoPartido::PendienteValidacion)
        ->and($partido->registrado_por_jugador_id)->toBe($this->ana->id);

    // Eva (misma pareja) no puede validar; un rival, sí.
    $this->actingAs($this->eva, 'jugador');
    Livewire::test(PortalDelJugador::class)->assertDontSee('Es correcto: validar')->call('validar', $partido->id)->assertHasErrors('portal');

    $rival = ($nosotrosLocal ? $partido->visitante : $partido->local)->jugadores->first();
    $this->actingAs($rival, 'jugador');
    Livewire::test(PortalDelJugador::class)->assertSee('Es correcto: validar')->call('validar', $partido->id)->assertHasNoErrors();

    expect($partido->refresh()->estado)->toBe(EstadoPartido::Jugado)
        ->and($partido->ganador_id)->toBe($partido->local_id);

    // Un partido que no es suyo, ni verlo.
    $ajeno = Partido::query()->where('local_id', '!=', $this->nuestra->id)->where('visitante_id', '!=', $this->nuestra->id)->firstOrFail();
    $this->actingAs($this->ana, 'jugador');
    Livewire::test(PortalDelJugador::class)->call('abrirResultado', $ajeno->id)->call('guardarResultado')->assertNotFound();
});

test('si el torneo no deja a los jugadores, no hay botón ni se puede anotar', function () {
    $this->torneo->update(['resultados_por_jugadores' => ResultadosPorJugadores::Desactivado]);
    $fase = sortearElTorneo($this->torneo, $this->categoria);
    $partido = $fase->partidos()->where('local_id', $this->nuestra->id)->orWhere('visitante_id', $this->nuestra->id)->firstOrFail();

    Livewire::test(PortalDelJugador::class)
        ->assertDontSee('Anotar resultado')
        ->call('abrirResultado', $partido->id)
        ->set('sets.0.juegos_local', 6)->set('sets.0.juegos_visitante', 3)
        ->set('sets.1.juegos_local', 6)->set('sets.1.juegos_visitante', 4)
        ->call('guardarResultado')
        ->assertHasErrors('resultado');

    expect($partido->refresh()->estado)->toBe(EstadoPartido::Pendiente);
});

test('el jugador anota cuándo no puede jugar antes de los horarios', function () {
    $dia = $this->torneo->fecha_inicio->toDateString();

    Livewire::test(PortalDelJugador::class)
        ->assertSee('Cuándo no puedo jugar')
        ->set("noDisponible.{$this->torneo->id}", ['dia' => $dia, 'desde' => '10:00', 'hasta' => '12:00'])
        ->call('anotarNoDisponible', $this->torneo->id)
        ->assertHasNoErrors();

    $hueco = Disponibilidad::query()->sole();
    expect($hueco->jugador_id)->toBe($this->ana->id)
        ->and($hueco->desde->format('Y-m-d H:i'))->toBe("{$dia} 10:00");

    Livewire::test(PortalDelJugador::class)
        ->set("noDisponible.{$this->torneo->id}", ['dia' => $dia, 'desde' => '12:00', 'hasta' => '10:00'])
        ->call('anotarNoDisponible', $this->torneo->id)
        ->assertHasErrors('portal');

    Livewire::test(PortalDelJugador::class)->call('quitarNoDisponible', $hueco->id)->assertHasNoErrors();
    expect(Disponibilidad::query()->count())->toBe(0);
});

test('el jugador cambia sus datos y puede borrarlos conservando los resultados', function () {
    Livewire::test(PortalDelJugador::class)
        ->set('alias', 'La Zurda')->set('telefono', '600000000')
        ->call('guardarDatos')
        ->assertSet('aviso', 'Datos guardados.');
    expect($this->ana->refresh()->alias)->toBe('La Zurda');

    Livewire::test(PortalDelJugador::class)->set('confirmarBorrado', 'no')->call('borrarMisDatos')->assertHasErrors('confirmarBorrado');

    Livewire::test(PortalDelJugador::class)->set('confirmarBorrado', 'borrar')->call('borrarMisDatos')->assertRedirect('/');

    $ana = $this->ana->refresh();
    expect($ana->anonimizado_en)->not->toBeNull()
        ->and($ana->getAttribute('email'))->toBeNull()
        ->and($ana->getAttribute('telefono'))->toBeNull()
        ->and($ana->alias)->toBeNull()
        ->and($this->nuestra->refresh()->nombre)->toBe('Anónimo / López')
        ->and(Inscripcion::query()->whereKey($this->inscripcion->id)->exists())->toBeTrue();
    $this->assertGuest('jugador');
});

test('el jugador cambia su nivel y la capitana también el de su compañera', function () {
    $this->ana->update(['nivel_declarado' => '3']);
    $this->eva->update(['nivel_declarado' => '2']);

    Livewire::test(PortalDelJugador::class)
        ->assertSee('Nivel de Eva')
        ->assertSet('nivel', '3')
        ->assertSet('nivelesDeCompaneros', [$this->eva->id => '2'])
        ->set('nivel', '3,5')
        ->call('guardarDatos')
        ->assertHasNoErrors()
        ->set("nivelesDeCompaneros.{$this->eva->id}", '2,75')
        ->call('guardarNivelDeCompanero', $this->eva->id)
        ->assertHasNoErrors()
        ->assertSee('Nivel de Eva guardado.')
        ->set('nivel', 'muy bueno')
        ->call('guardarDatos')
        ->assertHasErrors('nivel');

    expect($this->ana->refresh()->nivel_declarado)->toBe('3,5')
        ->and($this->eva->refresh()->nivel_declarado)->toBe('2,75')
        ->and($this->nuestra->refresh()->nivelMedioTexto())->toBe('Media 3,13')
        ->and(RegistroDeCambio::query()->where('accion', 'cambiar_nivel')->count())->toBe(2);
});

test('quien no es capitán solo cambia el suyo, y nadie el de otra pareja', function () {
    $this->actingAs($this->eva, 'jugador');

    Livewire::test(PortalDelJugador::class)
        ->assertDontSee('Nivel de Ana')
        ->assertSet('nivelesDeCompaneros', [])
        ->set('nivel', '4')
        ->call('guardarDatos')
        ->assertHasNoErrors()
        ->set("nivelesDeCompaneros.{$this->ana->id}", '1')
        ->call('guardarNivelDeCompanero', $this->ana->id)
        ->assertHasErrors("nivel.{$this->ana->id}");

    $rival = Inscripcion::query()->whereKeyNot($this->inscripcion->id)->firstOrFail()->participante->jugadores->last();
    expect(fn () => app(CambiarNivel::class)->handle($this->ana, $rival, '1'))->toThrow(OperacionNoPermitida::class)
        ->and($this->eva->refresh()->nivel_declarado)->toBe('4')
        ->and($this->ana->refresh()->nivel_declarado)->not->toBe('1');
});

test('con la pareja de baja o el torneo terminado, la capitana ya no cambia el nivel de su compañera', function () {
    $this->torneo->update(['estado' => EstadoTorneo::Finalizado]);

    expect(CambiarNivel::companeros($this->ana))->toHaveCount(0)
        ->and(CambiarNivel::puede($this->ana, $this->eva))->toBeFalse()
        ->and(CambiarNivel::puede($this->ana, $this->ana))->toBeTrue();
});
