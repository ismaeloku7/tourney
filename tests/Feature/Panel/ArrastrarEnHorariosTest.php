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
