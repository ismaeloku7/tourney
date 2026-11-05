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
use App\Livewire\Jugador\Inicio;
use App\Livewire\Jugador\MisDatos;
use App\Livewire\Jugador\MisPartidos;
use App\Livewire\Jugador\MiTorneo;
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
    $this->ana = Jugador::factory()->create(['nombre' => 'Ana', 'apellidos' => 'García', 'nivel_declarado' => null]);
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

function miTorneo(Inscripcion $inscripcion)
{
    return Livewire::test(MiTorneo::class, ['inscripcion' => $inscripcion]);
}

test('el inicio saluda, resume y lista sus torneos con la pareja y lo pendiente', function () {
    $this->get('http://norte.saas.test/jugador')->assertOk()
        ->assertSee('Hola, Ana')
        ->assertSee('1 torneo activo')
        ->assertSee('Open de Primavera')
        ->assertSee('García / López')
        ->assertSee('con Eva López')
        ->assertSee('Tu pareja')
        ->assertSee('Pon tu nivel de juego')
        ->assertSee('Saldrá cuando haya sorteo')
        ->assertSee(route('jugador.inscripcion', $this->inscripcion), false);

    // Con el sorteo hecho, el próximo partido y su rival.
    sortearElTorneo($this->torneo, $this->categoria);
    $this->ana->update(['nivel_declarado' => '3']);
    Livewire::test(Inicio::class)->assertSee('Contra ')->assertSee('Sin hora')->assertDontSee('Pon tu nivel de juego');
});

test('el portal tiene menú propio y las rutas antiguas de la inscripción siguen funcionando', function () {
    $this->get('http://norte.saas.test/jugador/partidos')->assertOk()->assertSee('Mis partidos')->assertSee('Nada por jugar');
    $this->get('http://norte.saas.test/jugador/avisos')->assertOk()->assertSee('Por dónde quieres los avisos');
    $this->get('http://norte.saas.test/jugador/datos')->assertOk()->assertSee('Mis datos')->assertSee('Borrar mis datos');
    $this->get("http://norte.saas.test/jugador/inscripciones/{$this->inscripcion->id}")->assertRedirect(route('jugador.inscripcion', $this->inscripcion));
});

test('desde su torneo el jugador se da de baja solo con la inscripción abierta', function () {
    miTorneo($this->inscripcion)
        ->assertSee('Darme de baja de este torneo')
        ->call('darmeDeBaja')
        ->assertSet('aviso', 'Te has dado de baja.');
    expect($this->inscripcion->refresh()->estado)->toBe(EstadoInscripcion::Baja);

    // Con la inscripción cerrada, ya no.
    $this->inscripcion->update(['estado' => EstadoInscripcion::Confirmada]);
    $this->torneo->update(['estado' => EstadoTorneo::InscripcionCerrada]);
    miTorneo($this->inscripcion->refresh())->assertDontSee('Darme de baja de este torneo')->call('darmeDeBaja')->assertHasErrors('portal');
    expect($this->inscripcion->refresh()->estado)->toBe(EstadoInscripcion::Confirmada);
});

test('su torneo enseña su grupo con su pareja resaltada, los otros grupos y el cruce previsto', function () {
    $fase = sortearElTorneo($this->torneo, $this->categoria);
    Fase::factory()->eliminatoria()->create(['categoria_id' => $this->categoria->id]);

    miTorneo($this->inscripcion)
        ->assertSee('Mi grupo: A')
        ->assertSee('tú')
        ->assertSee('García / López')
        ->assertSee('Cruce')
        ->assertSee('vs 2º A')
        ->assertDontSee('Los otros grupos');

    // Con dos grupos, el otro va aparte y plegado.
    $fase->update(['configuracion' => [...$fase->configuracion, 'tamano_de_grupo' => 2]]);
    app(SortearGrupos::class)->handle($fase->refresh(), User::factory()->rol(Rol::Propietario)->create(), forzar: true);
    miTorneo($this->inscripcion)->assertSee('Los otros grupos de Mixta B')->assertSee('Grupo B');
});

test('un jugador anota el resultado y solo la pareja rival lo valida', function () {
    $fase = sortearElTorneo($this->torneo, $this->categoria);
    $partido = $fase->partidos()->where(fn ($q) => $q->where('local_id', $this->nuestra->id)->orWhere('visitante_id', $this->nuestra->id))->orderBy('id')->firstOrFail();
    $nosotrosLocal = $partido->local_id === $this->nuestra->id;

    Livewire::test(MisPartidos::class)
        ->assertSee('Anotar resultado')
        ->call('abrirResultado', $partido->id)
        ->set('sets.0.juegos_local', 7)->set('sets.0.juegos_visitante', 3)
        ->set('sets.1.juegos_local', 6)->set('sets.1.juegos_visitante', 4)
        ->call('guardarResultado')
        ->assertHasErrors('resultado');

    // También desde la página del torneo.
    miTorneo($this->inscripcion)
        ->call('abrirResultado', $partido->id)
        ->set('sets.0.juegos_local', 6)->set('sets.0.juegos_visitante', 3)
        ->set('sets.1.juegos_local', 6)->set('sets.1.juegos_visitante', 4)
        ->call('guardarResultado')
        ->assertHasNoErrors()
        ->assertSet('aviso', 'Resultado anotado. Falta que lo valide la pareja rival.');

    expect($partido->refresh()->estado)->toBe(EstadoPartido::PendienteValidacion)
        ->and($partido->registrado_por_jugador_id)->toBe($this->ana->id);

    // Eva (misma pareja) no puede validar; un rival, sí, y lo ve en su inicio.
    $this->actingAs($this->eva, 'jugador');
    Livewire::test(MisPartidos::class)->assertDontSee('Es correcto: validar')->call('validar', $partido->id)->assertHasErrors('portal');

    $rival = ($nosotrosLocal ? $partido->visitante : $partido->local)->jugadores->first();
    $this->actingAs($rival, 'jugador');
    Livewire::test(Inicio::class)->assertSee('Validar el resultado que ha anotado tu rival');
    Livewire::test(MisPartidos::class)->assertSee('Es correcto: validar')->call('validar', $partido->id)->assertHasNoErrors();

    expect($partido->refresh()->estado)->toBe(EstadoPartido::Jugado)
        ->and($partido->ganador_id)->toBe($partido->local_id);

    // Un partido que no es suyo, ni verlo.
    $ajeno = Partido::query()->where('local_id', '!=', $this->nuestra->id)->where('visitante_id', '!=', $this->nuestra->id)->firstOrFail();
    $this->actingAs($this->ana, 'jugador');
    Livewire::test(MisPartidos::class)->call('abrirResultado', $ajeno->id)->call('guardarResultado')->assertNotFound();
});

test('si el torneo no deja a los jugadores, no hay botón ni se puede anotar', function () {
    $this->torneo->update(['resultados_por_jugadores' => ResultadosPorJugadores::Desactivado]);
    $fase = sortearElTorneo($this->torneo, $this->categoria);
    $partido = $fase->partidos()->where('local_id', $this->nuestra->id)->orWhere('visitante_id', $this->nuestra->id)->firstOrFail();

    Livewire::test(MisPartidos::class)
        ->assertDontSee('Anotar resultado')
        ->call('abrirResultado', $partido->id)
        ->set('sets.0.juegos_local', 6)->set('sets.0.juegos_visitante', 3)
        ->set('sets.1.juegos_local', 6)->set('sets.1.juegos_visitante', 4)
        ->call('guardarResultado')
        ->assertHasErrors('resultado');

    expect($partido->refresh()->estado)->toBe(EstadoPartido::Pendiente);
});

test('el jugador anota cuándo no puede jugar antes de los horarios, desde su torneo', function () {
    $dia = $this->torneo->fecha_inicio->toDateString();

    Livewire::test(Inicio::class)->assertSee('Dinos cuándo no puedes jugar');

    miTorneo($this->inscripcion)
        ->assertSee('Cuándo no puedo jugar')
        ->set('noDisponible', ['dia' => $dia, 'desde' => '10:00', 'hasta' => '12:00'])
        ->call('anotarNoDisponible')
        ->assertHasNoErrors();

    $hueco = Disponibilidad::query()->sole();
    expect($hueco->jugador_id)->toBe($this->ana->id)
        ->and($hueco->desde->format('Y-m-d H:i'))->toBe("{$dia} 10:00");

    Livewire::test(Inicio::class)->assertDontSee('Dinos cuándo no puedes jugar');

    miTorneo($this->inscripcion)
        ->set('noDisponible', ['dia' => $dia, 'desde' => '12:00', 'hasta' => '10:00'])
        ->call('anotarNoDisponible')
        ->assertHasErrors('portal');

    miTorneo($this->inscripcion)->call('quitarNoDisponible', $hueco->id)->assertHasNoErrors();
    expect(Disponibilidad::query()->count())->toBe(0);
});

test('el jugador cambia sus datos y puede borrarlos conservando los resultados', function () {
    Livewire::test(MisDatos::class)
        ->set('alias', 'La Zurda')->set('telefono', '600000000')
        ->call('guardarDatos')
        ->assertSet('aviso', 'Datos guardados.');
    expect($this->ana->refresh()->alias)->toBe('La Zurda');

    Livewire::test(MisDatos::class)->set('confirmarBorrado', 'no')->call('borrarMisDatos')->assertHasErrors('confirmarBorrado');

    Livewire::test(MisDatos::class)->set('confirmarBorrado', 'borrar')->call('borrarMisDatos')->assertRedirect('/');

    $ana = $this->ana->refresh();
    expect($ana->anonimizado_en)->not->toBeNull()
        ->and($ana->getAttribute('email'))->toBeNull()
        ->and($ana->getAttribute('telefono'))->toBeNull()
        ->and($ana->alias)->toBeNull()
        ->and($this->nuestra->refresh()->nombre)->toBe('Anónimo / López')
        ->and(Inscripcion::query()->whereKey($this->inscripcion->id)->exists())->toBeTrue();
    $this->assertGuest('jugador');
});

test('el jugador cambia su nivel desde sus datos', function () {
    $this->ana->update(['nivel_declarado' => '3']);

    Livewire::test(MisDatos::class)
        ->assertSet('nivel', '3')
        ->set('nivel', '3,5')
        ->call('guardarDatos')
        ->assertHasNoErrors()
        ->set('nivel', 'muy bueno')
        ->call('guardarDatos')
        ->assertHasErrors('nivel');

    expect($this->ana->refresh()->nivel_declarado)->toBe('3,5');
});

test('desde su torneo, cualquiera de la pareja pone el nivel de los dos, sea o no el capitán', function () {
    $this->ana->update(['nivel_declarado' => '3']);
    $this->eva->update(['nivel_declarado' => '2']);

    // Eva no es la capitana (Ana es la jugadora 1).
    $this->actingAs($this->eva, 'jugador');
    $this->get("http://norte.saas.test/jugador/torneos/{$this->inscripcion->id}")->assertOk()->assertSee('Open de Primavera')->assertSee('Mixta B');

    miTorneo($this->inscripcion)
        ->assertSet('niveles', [$this->ana->id => '3', $this->eva->id => '2'])
        ->set("niveles.{$this->ana->id}", '3,5')
        ->set("niveles.{$this->eva->id}", '2,75')
        ->call('guardarNiveles')
        ->assertHasNoErrors()
        ->assertSee('Niveles guardados.')
        ->assertSee('Media 3,13')
        ->set("niveles.{$this->ana->id}", 'muy bueno')
        ->call('guardarNiveles')
        ->assertHasErrors("niveles.{$this->ana->id}");

    expect($this->ana->refresh()->nivel_declarado)->toBe('3,5')
        ->and($this->eva->refresh()->nivel_declarado)->toBe('2,75')
        ->and(RegistroDeCambio::query()->where('accion', 'cambiar_nivel')->count())->toBe(2);
});

test('el torneo de otra pareja no se abre, y nadie cambia el nivel de otra pareja', function () {
    $otra = Inscripcion::query()->whereKeyNot($this->inscripcion->id)->firstOrFail();

    $this->get("http://norte.saas.test/jugador/torneos/{$otra->id}")->assertNotFound();

    $rival = $otra->participante->jugadores->last();
    expect(fn () => app(CambiarNivel::class)->handle($this->ana, $rival, '1'))->toThrow(OperacionNoPermitida::class)
        ->and(CambiarNivel::puede($this->eva, $this->ana))->toBeTrue()
        ->and(CambiarNivel::puede($this->ana, $this->eva))->toBeTrue();
});

test('con el torneo terminado, los niveles ya no se cambian desde su torneo', function () {
    $this->torneo->update(['estado' => EstadoTorneo::Finalizado]);

    expect(CambiarNivel::companeros($this->ana))->toHaveCount(0)
        ->and(CambiarNivel::puede($this->ana, $this->eva))->toBeFalse()
        ->and(CambiarNivel::puede($this->ana, $this->ana))->toBeTrue();

    miTorneo($this->inscripcion)
        ->assertSee('ya no se cambian')
        ->assertDontSee('Guardar niveles')
        ->call('guardarNiveles')
        ->assertForbidden();
});
