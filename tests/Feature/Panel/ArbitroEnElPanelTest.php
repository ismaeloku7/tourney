<?php

use App\Enums\EstadoPartido;
use App\Enums\EstadoTorneo;
use App\Enums\Rol;
use App\Filament\Resources\Jugadores\Pages\EditJugador;
use App\Filament\Resources\Jugadores\Pages\ListJugadores;
use App\Filament\Resources\Parejas\Pages\EditPareja;
use App\Filament\Resources\Parejas\Pages\ListParejas;
use App\Filament\Resources\Torneos\Pages\CompeticionDelTorneo;
use App\Filament\Resources\Torneos\Pages\EditTorneo;
use App\Filament\Resources\Torneos\Pages\HorariosDelTorneo;
use App\Filament\Resources\Torneos\Pages\ListTorneos;
use App\Filament\Resources\Torneos\Pages\PartidosDelTorneo;
use App\Filament\Resources\Torneos\Pages\ViewTorneo;
use App\Filament\Resources\Torneos\TorneoResource;
use App\Models\AccesoDirecto;
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
use App\Models\TorneoArbitro;
use App\Models\User;
use Carbon\CarbonImmutable;
use chillerlan\QRCode\QRCode;
use Database\Seeders\PlanesSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

/**
 * Un árbitro (o mesa) entra en el panel y lleva los partidos de los torneos
 * que tiene asignados: competición, partidos y horarios; resultados y
 * mover partidos; las parejas y los jugadores para mirar, con sus enlaces.
 */
beforeEach(function () {
    $this->seed(PlanesSeeder::class);
    usarOrganizador(Organizador::factory()->create());
    Filament::setCurrentPanel('panel');
    FormatoPartido::factory()->predeterminado()->create(['duracion_estimada' => 60]);

    $this->arbitro = User::factory()->rol(Rol::Arbitro)->create();
    $this->suyo = Torneo::factory()->create(['estado' => EstadoTorneo::EnJuego, 'descanso_minimo_minutos' => 0, 'margen_entre_partidos_minutos' => 10]);
    $this->ajeno = Torneo::factory()->create();
    TorneoArbitro::query()->create(['torneo_id' => $this->suyo->id, 'user_id' => $this->arbitro->id]);

    $categoria = Categoria::factory()->create(['torneo_id' => $this->suyo->id]);
    $this->fase = Fase::factory()->create(['categoria_id' => $categoria->id]);
    $this->dia = CarbonImmutable::parse($this->suyo->fecha_inicio->toDateString());
    [$this->uno, $this->dos] = Pista::factory()->count(2)->create()->all();
    foreach ([$this->uno, $this->dos] as $pista) {
        $this->suyo->torneoPistas()->create(['pista_id' => $pista->id, 'orden' => $pista->id, 'disponibilidad' => [['fecha' => $this->dia->toDateString(), 'desde' => '09:00', 'hasta' => '21:00']]]);
    }

    $this->ana = Jugador::factory()->create(['nombre' => 'Ana', 'apellidos' => 'García']);
    $this->pareja = Inscripcion::factory()->create([
        'participante_id' => Participante::factory()->conJugadores($this->ana, Jugador::factory()->create())->create(['categoria_id' => $categoria->id])->id,
    ]);
    $this->deFuera = Jugador::factory()->create();
    $this->parejaAjena = Inscripcion::factory()->create([
        'participante_id' => Participante::factory()->conJugadores($this->deFuera)->create(['categoria_id' => Categoria::factory()->create(['torneo_id' => $this->ajeno->id])->id])->id,
    ]);

    $this->actingAs($this->arbitro);
});

/** Un partido programado en una pista y una hora del torneo del árbitro. */
function partidoDelArbitro(Fase $fase, Pista $pista, CarbonImmutable $inicio): Partido
{
    $partido = Partido::factory()->create(['fase_id' => $fase->id, 'estado' => EstadoPartido::Programado]);
    $franja = $fase->categoria->torneo->franjas()->create(['pista_id' => $pista->id, 'inicio' => $inicio, 'fin' => $inicio->addMinutes(70), 'inicio_previsto' => $inicio]);
    $partido->update(['franja_id' => $franja->id, 'pista_id' => $pista->id]);

    return $partido->refresh();
}

test('ve solo sus torneos y de cada uno la competición, los partidos y los horarios; ni la ficha ni editar', function () {
    $lista = Livewire::test(ListTorneos::class)
        ->assertCanSeeTableRecords([$this->suyo])
        ->assertCanNotSeeTableRecords([$this->ajeno]);
    expect($lista->instance()->getTable()->getRecordUrl($this->suyo))->toBe(TorneoResource::getUrl('partidos', ['record' => $this->suyo]));

    $ruta = ['record' => $this->suyo->getRouteKey()];
    Livewire::test(PartidosDelTorneo::class, $ruta)->assertOk()
        ->assertActionHidden('ficha')
        ->assertActionHidden('editar')
        ->assertActionVisible('competicion')
        ->assertActionVisible('horarios');
    Livewire::test(CompeticionDelTorneo::class, $ruta)->assertOk();
    Livewire::test(HorariosDelTorneo::class, $ruta)->assertOk()->assertActionHidden('calcular');

    // La ficha lo lleva a los partidos; editar y los torneos de otros, no.
    Livewire::test(ViewTorneo::class, $ruta)->assertRedirect(TorneoResource::getUrl('partidos', ['record' => $this->suyo]));
    Livewire::test(EditTorneo::class, $ruta)->assertForbidden();
    Livewire::test(PartidosDelTorneo::class, ['record' => $this->ajeno->getRouteKey()])->assertNotFound();
});

test('pone resultados y mueve partidos de hora y de pista', function () {
    $partido = partidoDelArbitro($this->fase, $this->uno, $this->dia->setTime(9, 0));
    $otro = partidoDelArbitro($this->fase, $this->uno, $this->dia->setTime(10, 10));

    Livewire::test(PartidosDelTorneo::class, ['record' => $this->suyo->getRouteKey()])
        ->callAction(TestAction::make('resultado')->table($partido), ['sets' => [['juegos_local' => 6, 'juegos_visitante' => 3], ['juegos_local' => 6, 'juegos_visitante' => 4]]])
        ->assertHasNoActionErrors()
        ->assertNotified('Resultado registrado');
    expect($partido->refresh()->estado)->toBe(EstadoPartido::Jugado);

    Livewire::test(HorariosDelTorneo::class, ['record' => $this->suyo->getRouteKey()])
        ->call('moverPartido', $otro->id, $this->dos->id, $this->dia->setTime(12, 0)->getTimestamp())
        ->assertNotified('Partido movido');
    expect($otro->refresh()->pista_id)->toBe($this->dos->id)
        ->and($otro->franja->inicio->format('H:i'))->toBe('12:00');
});

test('ve las parejas y los jugadores de sus torneos con el enlace de cada pareja, sin cambiarlos', function () {
    Livewire::test(ListParejas::class)
        ->assertCanSeeTableRecords([$this->pareja])
        ->assertCanNotSeeTableRecords([$this->parejaAjena])
        ->assertActionHidden(TestAction::make('editar')->table($this->pareja))
        ->assertActionHidden(TestAction::make('mover')->table($this->pareja))
        ->assertActionHidden(TestAction::make('crearAccesos')->table())
        ->assertActionHidden(TestAction::make('borrarAccesos')->table())
        ->callAction(TestAction::make('crearAcceso')->table($this->pareja))
        ->assertNotified('Enlace creado');
    expect(AccesoDirecto::query()->where('inscripcion_id', $this->pareja->id)->exists())->toBeTrue();

    Livewire::test(ListParejas::class)
        ->mountAction(TestAction::make('verAcceso')->table($this->pareja))
        ->assertActionMounted(TestAction::make('verAcceso')->table($this->pareja));
    Livewire::test(EditPareja::class, ['record' => $this->pareja->getRouteKey()])->assertForbidden();

    Livewire::test(ListJugadores::class)
        ->assertCanSeeTableRecords([$this->ana])
        ->assertCanNotSeeTableRecords([$this->deFuera])
        ->assertActionHidden(TestAction::make('edit')->table($this->ana));
    // A los de sus torneos los ve, pero no los edita.
    Livewire::test(EditJugador::class, ['record' => $this->ana->getRouteKey()])->assertForbidden();
});

test('el enlace de cada pareja lleva su QR para escanearlo', function () {
    $html = view('filament.tablas.enlace-de-acceso', ['url' => 'https://ejemplo.test/acceso/abc', 'qr' => (new QRCode)->render('https://ejemplo.test/acceso/abc'), 'jugador' => 'Ana García', 'ultimoUso' => null])->render();

    expect($html)->toContain('src="data:image/svg+xml;base64,')
        ->and($html)->toContain('https://ejemplo.test/acceso/abc')
        ->and($html)->toContain('Entra como Ana García');
});
