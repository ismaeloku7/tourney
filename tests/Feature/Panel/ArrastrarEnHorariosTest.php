<?php

use App\Actions\Competicion\PlanificarHorarios;
use App\Enums\EstadoPartido;
use App\Enums\EstadoTorneo;
use App\Enums\Rol;
use App\Filament\Resources\Torneos\Pages\HorariosDelTorneo;
use App\Models\Categoria;
use App\Models\Fase;
use App\Models\FormatoPartido;
use App\Models\Organizador;
use App\Models\Partido;
use App\Models\Pista;
use App\Models\Torneo;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\PlanesSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

/**
 * En la vista de horarios los partidos se arrastran: a otra pista u hora,
 * o sobre otro para intercambiarlos. Dos pistas y partidos de 70 minutos.
 */
beforeEach(function () {
    $this->seed(PlanesSeeder::class);
    usarOrganizador(Organizador::factory()->create());
    Filament::setCurrentPanel('panel');
    FormatoPartido::factory()->predeterminado()->create(['duracion_estimada' => 60]);

    $this->actingAs(User::factory()->rol(Rol::Propietario)->create());
    $this->torneo = Torneo::factory()->create(['estado' => EstadoTorneo::Sorteo, 'descanso_minimo_minutos' => 0, 'margen_entre_partidos_minutos' => 10]);
    $this->fase = Fase::factory()->create(['categoria_id' => Categoria::factory()->create(['torneo_id' => $this->torneo->id])->id]);
    $this->dia = CarbonImmutable::parse($this->torneo->fecha_inicio->toDateString());
    [$this->uno, $this->dos] = Pista::factory()->count(2)->create()->all();
    foreach ([$this->uno, $this->dos] as $pista) {
        $this->torneo->torneoPistas()->create(['pista_id' => $pista->id, 'orden' => $pista->id, 'disponibilidad' => [['fecha' => $this->dia->toDateString(), 'desde' => '09:00', 'hasta' => '21:00']]]);
    }
});

function colocadoEn(Fase $fase, Pista $pista, CarbonImmutable $inicio, string $estado = 'programado', array $atributos = []): Partido
{
    $partido = Partido::factory()->create(['fase_id' => $fase->id, 'estado' => $estado, ...$atributos]);
    $franja = $fase->categoria->torneo->franjas()->create(['pista_id' => $pista->id, 'inicio' => $inicio, 'fin' => $inicio->addMinutes(70), 'inicio_previsto' => $inicio]);
    $partido->update(['franja_id' => $franja->id, 'pista_id' => $pista->id]);

    return $partido->refresh();
}

test('se arrastra a otra pista: se pega al anterior, empuja a los de detrás y en la suya suben los que iban pegados', function () {
    $a = colocadoEn($this->fase, $this->uno, $this->dia->setTime(9, 0));
    $b = colocadoEn($this->fase, $this->uno, $this->dia->setTime(10, 10));
    $c = colocadoEn($this->fase, $this->dos, $this->dia->setTime(9, 0));
    $d = colocadoEn($this->fase, $this->dos, $this->dia->setTime(10, 10));

    // $a a la pista 2 a las 10:15 (dentro de $d): va detrás de $d.
    Livewire::test(HorariosDelTorneo::class, ['record' => $this->torneo->getRouteKey()])
        ->call('moverPartido', $a->id, $this->dos->id, $this->dia->setTime(10, 15)->getTimestamp())
        ->assertNotified('Movido: cambian 2 partidos');

    expect($a->refresh()->pista_id)->toBe($this->dos->id)
        ->and($a->franja->pista_id)->toBe($this->dos->id)
        ->and($a->franja->inicio->format('H:i'))->toBe('11:20')
        ->and($a->franja->fin->format('H:i'))->toBe('12:30')
        // En la pista 1, $b sube a las 9:00.
        ->and($b->refresh()->franja->inicio->format('H:i'))->toBe('09:00')
        ->and($c->refresh()->franja->inicio->format('H:i'))->toBe('09:00')
        ->and($d->refresh()->franja->inicio->format('H:i'))->toBe('10:10');
});

test('soltado sobre otro, se intercambian; lo jugado no se mueve', function () {
    $a = colocadoEn($this->fase, $this->uno, $this->dia->setTime(9, 0));
    $b = colocadoEn($this->fase, $this->dos, $this->dia->setTime(10, 10));
    $jugado = colocadoEn($this->fase, $this->dos, $this->dia->setTime(9, 0), EstadoPartido::Jugado->value);

    $pagina = Livewire::test(HorariosDelTorneo::class, ['record' => $this->torneo->getRouteKey()])
        ->call('intercambiarPartidos', $a->id, $b->id)
        ->assertNotified('Movido: cambian 2 partidos');

    expect($a->refresh()->franja->pista_id)->toBe($this->dos->id)
        ->and($a->franja->inicio->format('H:i'))->toBe('10:10')
        ->and($b->refresh()->franja->pista_id)->toBe($this->uno->id)
        ->and($b->franja->inicio->format('H:i'))->toBe('09:00');

    $pagina->call('moverPartido', $jugado->id, $this->uno->id, $this->dia->setTime(12, 0)->getTimestamp())
        ->assertNotified('No se ha movido');
    expect($jugado->refresh()->franja->pista_id)->toBe($this->dos->id);
});

test('el tercer partido seguido de una pareja lleva un aviso en la vista', function () {
    $a = colocadoEn($this->fase, $this->uno, $this->dia->setTime(9, 0));
    $pareja = ['local_id' => $a->local_id];
    colocadoEn($this->fase, $this->uno, $this->dia->setTime(10, 10), atributos: $pareja);

    Livewire::test(HorariosDelTorneo::class, ['record' => $this->torneo->getRouteKey()])
        ->assertDontSee('¡Alguien juega su tercer partido seguido!');

    // El tercero en la otra pista, a los 5 minutos de acabar el segundo.
    colocadoEn($this->fase, $this->dos, $this->dia->setTime(11, 15), atributos: $pareja);

    Livewire::test(HorariosDelTorneo::class, ['record' => $this->torneo->getRouteKey()])
        ->assertSee('¡Alguien juega su tercer partido seguido!');
});

test('uno que no cabía en la propuesta se arrastra al horario y se guarda al aplicarla', function () {
    $colocado = colocadoEn($this->fase, $this->uno, $this->dia->setTime(9, 0));
    $sinSitio = Partido::factory()->create(['fase_id' => $this->fase->id, 'estado' => 'pendiente']);
    $huella = app(PlanificarHorarios::class)->huella($this->torneo);

    $pagina = Livewire::test(HorariosDelTorneo::class, ['record' => $this->torneo->getRouteKey()])
        ->set('propuesta', [
            'colocados' => [],
            'conflictos' => [[$sinSitio->id, 'sin_franja', 'No queda hueco.']],
            'fin' => null, 'muertos' => 0, 'ocupacion' => 0,
            'partidos' => [$sinSitio->id], 'huella' => $huella, 'rehacer' => false,
        ])
        ->assertSee('1 partido no cabe')
        // Soltado a las 9:20 en la pista 1: dentro del colocado, va detrás de él.
        ->call('moverPartido', $sinSitio->id, $this->uno->id, $this->dia->setTime(9, 20)->getTimestamp())
        ->assertNotified('Partido movido')
        ->assertDontSee('1 partido no cabe');

    expect($pagina->get('propuesta')['colocados'])->toBe([[$sinSitio->id, $this->uno->id, $this->dia->setTime(10, 10)->getTimestamp(), $this->dia->setTime(11, 20)->getTimestamp()]]);

    $pagina->callAction('aplicar')->assertNotified('1 partidos con hora y pista');
    expect($sinSitio->refresh()->franja->inicio->format('H:i'))->toBe('10:10')
        ->and($colocado->refresh()->franja->inicio->format('H:i'))->toBe('09:00');
});

test('la parrilla va más allá del horario de las pistas y admite dejar ahí un partido a mano', function () {
    $a = colocadoEn($this->fase, $this->uno, $this->dia->setTime(9, 0));

    // Las pistas abren de 9 a 21: la parrilla va de 8 a 24, y el tramo abierto se dibuja aparte.
    $pagina = Livewire::test(HorariosDelTorneo::class, ['record' => $this->torneo->getRouteKey()])
        ->assertSee('Fuera del horario de la pista');
    $dia = $pagina->viewData('dias')[0];
    expect($dia['base'])->toBe(8 * 60)
        ->and($dia['minutos'])->toBe(16 * 60 - 4)
        ->and($dia['pistas'][0]['abre'])->toHaveCount(1)
        ->and($dia['pistas'][0]['abre'][0]['izquierda'])->toBe(round(100 * 60 / (16 * 60 - 4), 3));

    // Un partido se deja a las 22:00, fuera del horario de la pista, y se guarda.
    $pagina->call('moverPartido', $a->id, $this->dos->id, $this->dia->setTime(22, 0)->getTimestamp())->assertNotified('Partido movido');
    expect($a->refresh()->pista_id)->toBe($this->dos->id)->and($a->franja->inicio->format('H:i'))->toBe('22:00');
});

test('lo que empieza y acaba se ve a su hora real, de 5 en 5 minutos, con la línea de ahora y la pelota en lo que se juega', function () {
    $this->torneo->update(['estado' => EstadoTorneo::EnJuego]);
    $this->travelTo($this->dia->setTime(10, 42));

    // Previsto a las 9:00; empezó a las 9:23 y acabó a las 10:31.
    $jugado = colocadoEn($this->fase, $this->uno, $this->dia->setTime(9, 0), EstadoPartido::Jugado->value, ['empezado_en' => $this->dia->setTime(9, 23), 'registrado_en' => $this->dia->setTime(10, 31)]);
    // Previsto a las 9:00; nadie lo empezó: se queda donde estaba, aunque se solape.
    $sinApuntar = colocadoEn($this->fase, $this->dos, $this->dia->setTime(9, 0));
    // Previsto a las 10:10; empezó a las 10:02 y se está jugando: hasta su final estimado (60 minutos).
    $enJuego = colocadoEn($this->fase, $this->dos, $this->dia->setTime(10, 10), EstadoPartido::EnJuego->value, ['empezado_en' => $this->dia->setTime(10, 2)]);

    $pagina = Livewire::test(HorariosDelTorneo::class, ['record' => $this->torneo->getRouteKey()])
        ->assertSeeHtml('wire:poll.60s')
        ->assertSee('🎾');
    $dia = $pagina->viewData('dias')[0];
    $bloque = fn (Partido $p): array => collect($dia['pistas'])->flatMap(fn (array $pista): array => $pista['bloques'])->firstWhere('partido', $p->id);
    $minuto = fn (string $hora): float => round(100 * (CarbonImmutable::parse($this->dia->toDateString().' '.$hora)->diffInMinutes($this->dia->setTime(8, 0), true)) / $dia['minutos'], 3);

    expect($bloque($jugado))->toMatchArray(['real' => true, 'enJuego' => false, 'hora' => '09:25', 'izquierda' => $minuto('09:25'), 'calentamiento' => 0.0])
        ->and($bloque($jugado)['detalle'])->toContain('Se jugó de 09:25 a 10:30')
        ->and($bloque($sinApuntar))->toMatchArray(['real' => false, 'hora' => '09:00'])
        ->and($bloque($enJuego))->toMatchArray(['real' => true, 'enJuego' => true, 'hora' => '10:00'])
        ->and($bloque($enJuego)['ancho'])->toBe(round(100 * 60 / $dia['minutos'], 3))
        ->and($dia['ahora'])->toBe(['izquierda' => $minuto('10:42'), 'hora' => '10:42']);
});
