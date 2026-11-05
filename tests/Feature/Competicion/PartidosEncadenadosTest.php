<?php

use App\Actions\Competicion\AnotarMarcador;
use App\Actions\Competicion\EmpezarPartido;
use App\Actions\Competicion\EncadenarPartidos;
use App\Actions\Competicion\GenerarFranjas;
use App\Actions\Competicion\GenerarPartidos;
use App\Actions\Competicion\PlanificarHorarios;
use App\Actions\Competicion\RegistrarResultado;
use App\Actions\Competicion\SortearGrupos;
use App\Avisos\EnlaceDeWhatsApp;
use App\Competicion\Datos\Resultado;
use App\Competicion\Datos\Set;
use App\Competicion\Errores\OperacionNoPermitida;
use App\Enums\EstadoPartido;
use App\Enums\EstadoTorneo;
use App\Enums\ModoDeHorario;
use App\Enums\ResultadosPorJugadores;
use App\Enums\Rol;
use App\Livewire\Jugador\MisPartidos;
use App\Livewire\Mesa\MesaDeArbitro;
use App\Livewire\Publico\FichaDelTorneo;
use App\Models\Categoria;
use App\Models\Fase;
use App\Models\FormatoPartido;
use App\Models\Inscripcion;
use App\Models\Jugador;
use App\Models\Organizador;
use App\Models\Participante;
use App\Models\Partido;
use App\Models\Pista;
use App\Models\Torneo;
use App\Models\User;
use App\Notifications\SiguienteEnPista;
use Database\Seeders\PlanesSeeder;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/**
 * Un torneo encadenado hoy: 8 parejas en dos grupos de 4, dos pistas
 * desde las 9, partidos de una hora, calentamientos de 15 y 5 minutos.
 */
beforeEach(function () {
    $this->seed(PlanesSeeder::class);
    usarOrganizador(Organizador::factory()->create(['id' => 'norte']));
    FormatoPartido::factory()->predeterminado()->create(['duracion_estimada' => 60]);
    $this->propietario = User::factory()->rol(Rol::Propietario)->create();

    $this->torneo = Torneo::factory()->create([
        'estado' => EstadoTorneo::Sorteo, 'fecha_inicio' => today()->toDateString(), 'fecha_fin' => today()->toDateString(),
        'descanso_minimo_minutos' => 0, 'modo_horario' => ModoDeHorario::Encadenado, 'calentamiento_primero' => 15, 'calentamiento_resto' => 5,
        'resultados_por_jugadores' => ResultadosPorJugadores::ConValidacion,
    ]);
    [$this->central, $this->dos] = Pista::factory()->count(2)->create()->all();
    foreach ([$this->central, $this->dos] as $orden => $pista) {
        $this->torneo->torneoPistas()->create(['pista_id' => $pista->id, 'orden' => $orden + 1, 'disponibilidad' => [
            ['fecha' => today()->toDateString(), 'desde' => '09:00', 'hasta' => '23:00'],
        ]]);
    }

    $categoria = Categoria::factory()->create(['torneo_id' => $this->torneo->id]);
    foreach (range(1, 8) as $i) {
        Inscripcion::factory()->create(['participante_id' => Participante::factory()->create(['categoria_id' => $categoria->id, 'nombre' => "Pareja {$i}"])->id]);
    }
    $fase = Fase::factory()->grupos(['semilla' => 2])->create(['categoria_id' => $categoria->id]);
    app(SortearGrupos::class)->handle($fase, $this->propietario);
    app(GenerarPartidos::class)->handle($fase, $this->propietario);
    app(GenerarFranjas::class)->handle($this->torneo);
    app(PlanificarHorarios::class)->handle($this->torneo, $this->propietario);

    $this->travelTo(today()->setTime(8, 30));
    app(EncadenarPartidos::class)->handle($this->torneo, $this->propietario);
    $this->torneo->update(['estado' => EstadoTorneo::EnJuego, 'horarios_publicados_en' => now()]);
});

function colaDe(Pista $pista)
{
    return Partido::query()->where('pista_id', $pista->id)->whereNotNull('orden_en_pista')->orderBy('orden_en_pista')->with(['local.jugadores', 'visitante.jugadores'])->get();
}

test('encadenar numera los partidos por pista y estima el primero a la apertura más el calentamiento', function () {
    $cola = colaDe($this->central);

    expect($cola->count() + colaDe($this->dos)->count())->toBe(12)
        ->and($cola->pluck('orden_en_pista')->all())->toBe(range(1, $cola->count()))
        ->and($cola->first()->inicio_estimado->format('H:i'))->toBe('09:15')
        ->and($cola->get(1)->inicio_estimado->format('H:i'))->toBe('10:30')
        ->and(colaDe($this->dos)->first()->orden_en_pista)->toBe(1);
});

test('empezar, llevar el marcador y cerrar un partido avisa a la pareja siguiente y mueve su hora', function () {
    Notification::fake();
    [$primero, $segundo] = colaDe($this->central)->take(2)->all();
    $siguientes = [...$segundo->local->jugadores, ...$segundo->visitante->jugadores];

    $this->travelTo(today()->setTime(9, 10));
    $primero = app(EmpezarPartido::class)->handle($primero, $this->propietario);

    expect($primero->estado)->toBe(EstadoPartido::EnJuego)
        ->and($primero->empezado_en->format('H:i'))->toBe('09:10')
        ->and($segundo->refresh()->inicio_estimado->format('H:i'))->toBe('10:25')
        ->and($segundo->avisos_siguiente)->toHaveKey('empieza');
    Notification::assertSentTo($siguientes, SiguienteEnPista::class, fn (SiguienteEnPista $aviso): bool => $aviso->motivo === 'empieza' && $aviso->partido->is($segundo));

    // Empezar dos veces, no.
    expect(fn () => app(EmpezarPartido::class)->handle($primero, $this->propietario))->toThrow(OperacionNoPermitida::class);

    // Marcador: con el primer set cerrado, aviso de último set, y solo una vez.
    app(AnotarMarcador::class)->handle($primero, [], [3, 2], $this->propietario);
    expect(AnotarMarcador::texto($primero->refresh()))->toBe('3-2');
    Notification::assertNotSentTo($siguientes, SiguienteEnPista::class, fn (SiguienteEnPista $aviso): bool => $aviso->motivo === 'ultimo_set');

    app(AnotarMarcador::class)->handle($primero, [[6, 3]], [1, 0], $this->propietario);
    app(AnotarMarcador::class)->handle($primero, [[6, 3]], [2, 0], $this->propietario);
    expect(AnotarMarcador::texto($primero->refresh()))->toBe('6-3 2-0');
    Notification::assertSentToTimes($siguientes[0], SiguienteEnPista::class, 2);

    // El resultado final cierra el partido, libera la pista y al siguiente le toca.
    $this->travelTo(today()->setTime(9, 40));
    $primero = app(RegistrarResultado::class)->handle($primero, new Resultado([new Set(6, 3), new Set(6, 1)]), $this->propietario);

    expect($primero->estado)->toBe(EstadoPartido::Jugado)
        ->and($primero->terminado_en->format('H:i'))->toBe('09:40')
        ->and($primero->marcador_en_vivo)->toBeNull()
        ->and($segundo->refresh()->inicio_estimado->format('H:i'))->toBe('09:55');
    Notification::assertSentTo($siguientes, SiguienteEnPista::class, fn (SiguienteEnPista $aviso): bool => $aviso->motivo === 'os_toca');
    Notification::assertSentToTimes($siguientes[0], SiguienteEnPista::class, 3);
});

test('un jugador de la pareja lleva el marcador si el torneo lo permite; otro jugador, no', function () {
    $primero = colaDe($this->central)->first();
    $mio = $primero->local->jugadores->first();
    $ajeno = Jugador::factory()->create();

    expect(fn () => app(EmpezarPartido::class)->handle($primero, $ajeno))->toThrow(OperacionNoPermitida::class);

    $this->actingAs($mio, 'jugador');
    Livewire::test(MisPartidos::class)
        ->assertSee('Empezamos')
        ->call('empezarPartido', $primero->id)
        ->assertHasNoErrors()
        ->assertSee('Marcador en vivo')
        ->call('juego', $primero->id, true)
        ->call('juego', $primero->id, true)
        ->call('juego', $primero->id, false)
        ->call('cerrarSet', $primero->id)
        ->call('terminarPartido', $primero->id)
        ->assertSet('sets.0.juegos_local', 2)
        ->assertSet('sets.0.juegos_visitante', 1)
        ->assertSet('editando', $primero->id);

    expect($primero->refresh()->estado)->toBe(EstadoPartido::EnJuego)
        ->and($primero->marcador_en_vivo)->toBe(['sets' => [[2, 1]], 'juegos' => [0, 0]]);

    // Con los jugadores desactivados, ni empezar ni marcar.
    $this->torneo->update(['resultados_por_jugadores' => ResultadosPorJugadores::Desactivado]);
    $otro = colaDe($this->dos)->first();
    expect(fn () => app(EmpezarPartido::class)->handle($otro, $otro->local->jugadores->first()))->toThrow(OperacionNoPermitida::class);
});

test('la mesa empieza el partido, lleva el marcador, lo termina con los sets puestos y avisa por WhatsApp a los siguientes', function () {
    [$primero, $segundo] = colaDe($this->central)->take(2)->all();
    $segundo->local->jugadores->first()->update(['nombre' => 'Lola', 'telefono' => '600 11 22 33']);
    $this->actingAs($this->propietario);

    $mesa = Livewire::test(MesaDeArbitro::class, ['torneo' => $this->torneo->id, 'pista' => $this->central->id])
        ->assertSee('~09:15')
        ->assertSee('Empezar partido')
        ->assertSee('Avisar por WhatsApp')
        ->assertSeeHtml('https://wa.me/34600112233?text=')
        ->call('empezarPartido', $primero->id)
        ->assertHasNoErrors()
        ->assertSet('aviso', 'Partido en pista.')
        ->call('juego', $primero->id, true)
        ->call('cerrarSet', $primero->id)
        ->assertSee('1-0')
        ->call('terminarPartido', $primero->id)
        ->assertSet('sets.0.juegos_local', 1)
        ->set('sets.0.juegos_local', 6)->set('sets.0.juegos_visitante', 2)
        ->set('sets.1.juegos_local', 6)->set('sets.1.juegos_visitante', 3)
        ->call('guardarResultado')
        ->assertHasNoErrors()
        ->assertSet('aviso', 'Resultado guardado.');

    expect($primero->refresh()->estado)->toBe(EstadoPartido::Jugado)->and($primero->terminado_en)->not->toBeNull();

    // La ficha pública enseña la hora estimada con «~».
    Livewire::test(FichaDelTorneo::class, ['torneo' => $this->torneo])->call('ver', 'grupos')->assertSee('~');
});

test('el enlace de WhatsApp normaliza el teléfono y falta sin teléfono', function () {
    expect(EnlaceDeWhatsApp::numero('600 11 22 33'))->toBe('34600112233')
        ->and(EnlaceDeWhatsApp::numero('+34 600112233'))->toBe('34600112233')
        ->and(EnlaceDeWhatsApp::numero('0033 612345678'))->toBe('33612345678')
        ->and(EnlaceDeWhatsApp::numero('12345'))->toBeNull()
        ->and(EnlaceDeWhatsApp::para(Jugador::factory()->make(['nombre' => 'Ana', 'telefono' => null]), 'Hola'))->toBeNull()
        ->and(EnlaceDeWhatsApp::para(Jugador::factory()->make(['telefono' => '600112233']), 'Hola Ana'))->toBe('https://wa.me/34600112233?text=Hola%20Ana');
});
