<?php

use App\Actions\Competicion\HorasEnDirecto;
use App\Actions\Competicion\RegistrarResultado;
use App\Competicion\Datos\Resultado;
use App\Competicion\Datos\Set;
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
use App\Notifications\ACalentar;
use App\Notifications\CambioDePista;
use App\Notifications\SoisLosSiguientes;
use Carbon\CarbonImmutable;
use Database\Seeders\PlanesSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/**
 * Un torneo en juego con los horarios publicados, dos pistas y partidos de
 * 70 minutos (10 de calentamiento y 60 de juego) colocados a mano.
 */
beforeEach(function () {
    $this->seed(PlanesSeeder::class);
    usarOrganizador(Organizador::factory()->create());
    Filament::setCurrentPanel('panel');
    FormatoPartido::factory()->predeterminado()->create(['duracion_estimada' => 60]);

    $this->propietario = User::factory()->rol(Rol::Propietario)->create();
    $this->torneo = Torneo::factory()->create(['estado' => EstadoTorneo::EnJuego, 'horarios_publicados_en' => now(), 'descanso_minimo_minutos' => 30, 'margen_entre_partidos_minutos' => 10]);
    $this->categoria = Categoria::factory()->create(['torneo_id' => $this->torneo->id]);
    $this->fase = Fase::factory()->create(['categoria_id' => $this->categoria->id, 'estado' => 'en_juego']);
    $this->dia = CarbonImmutable::parse($this->torneo->fecha_inicio->toDateString());
    $this->pistas = Pista::factory()->count(2)->create();
    foreach ($this->pistas as $pista) {
        $this->torneo->torneoPistas()->create(['pista_id' => $pista->id, 'orden' => $pista->id, 'disponibilidad' => [['fecha' => $this->dia->toDateString(), 'desde' => '09:00', 'hasta' => '21:00']]]);
    }
});

/** Un partido de dos parejas nuevas en esa pista a esa hora (prevista igual). */
function partidoEn(Fase $fase, Pista $pista, CarbonImmutable $inicio): Partido
{
    $partido = Partido::factory()->create(['fase_id' => $fase->id, 'estado' => 'programado']);
    $franja = $fase->categoria->torneo->franjas()->create(['pista_id' => $pista->id, 'inicio' => $inicio, 'fin' => $inicio->addMinutes(70), 'inicio_previsto' => $inicio]);
    $partido->update(['franja_id' => $franja->id, 'pista_id' => $pista->id]);

    return $partido->refresh();
}

function resultadoDe(Partido $partido, User $quien): void
{
    app(RegistrarResultado::class)->handle($partido->refresh(), new Resultado([new Set(6, 2), new Set(6, 1)]), $quien);
}

test('al acabar un partido antes de tiempo, el siguiente de la pista pasa a calentar ya y el de después es el siguiente', function () {
    $pista = $this->pistas[0];
    $primero = partidoEn($this->fase, $pista, $this->dia->setTime(9, 0));
    $segundo = partidoEn($this->fase, $pista, $this->dia->setTime(10, 10));
    $tercero = partidoEn($this->fase, $pista, $this->dia->setTime(11, 20));

    Notification::fake();
    $this->travelTo($this->dia->setTime(9, 50));
    resultadoDe($primero, $this->propietario);

    // El segundo entra a las 9:50 (20 minutos antes) y el tercero detrás.
    expect($segundo->refresh()->franja->inicio->format('H:i'))->toBe('09:50')
        ->and($tercero->refresh()->franja->inicio->format('H:i'))->toBe('11:00')
        ->and($segundo->franja->inicio_previsto->format('H:i'))->toBe('10:10')
        ->and($segundo->aviso_calentar_en)->not->toBeNull()
        ->and($tercero->aviso_siguientes_en)->not->toBeNull();

    Notification::assertSentTo([...$segundo->local->jugadores, ...$segundo->visitante->jugadores], ACalentar::class);
    Notification::assertSentTo([...$tercero->local->jugadores, ...$tercero->visitante->jugadores], SoisLosSiguientes::class);
    Notification::assertNotSentTo($tercero->local->jugadores, ACalentar::class);

    // Cuando acaba el segundo (tarde, a las 11:15), al tercero le toca calentar; no se le repite «sois los siguientes».
    $this->travelTo($this->dia->setTime(11, 15));
    resultadoDe($segundo, $this->propietario);

    expect($tercero->refresh()->franja->inicio->format('H:i'))->toBe('11:15');
    Notification::assertSentTo($tercero->local->jugadores->first(), ACalentar::class);
    Notification::assertSentToTimes($tercero->local->jugadores->first(), SoisLosSiguientes::class, 1);
});

test('de una fase oculta no se avisa a nadie, aunque las horas se recalculen', function () {
    $this->fase->update(['visible_en_web' => false]);
    $pista = $this->pistas[0];
    $primero = partidoEn($this->fase, $pista, $this->dia->setTime(9, 0));
    $segundo = partidoEn($this->fase, $pista, $this->dia->setTime(10, 10));

    Notification::fake();
    $this->travelTo($this->dia->setTime(9, 50));
    resultadoDe($primero, $this->propietario);

    expect($segundo->refresh()->franja->inicio->format('H:i'))->toBe('09:50');
    Notification::assertNotSentTo($segundo->local->jugadores, ACalentar::class);
});

test('si una pista va 20 minutos tarde, la página de horarios lo propone y al aplicarlo se avisa a la pareja', function () {
    [$uno, $dos] = [$this->pistas[0], $this->pistas[1]];
    $a = partidoEn($this->fase, $uno, $this->dia->setTime(9, 0));
    partidoEn($this->fase, $uno, $this->dia->setTime(10, 10));
    partidoEn($this->fase, $uno, $this->dia->setTime(11, 20));
    $d = partidoEn($this->fase, $dos, $this->dia->setTime(9, 0));
    partidoEn($this->fase, $dos, $this->dia->setTime(10, 10));
    partidoEn($this->fase, $dos, $this->dia->setTime(11, 20));
    partidoEn($this->fase, $dos, $this->dia->setTime(12, 30));
    partidoEn($this->fase, $dos, $this->dia->setTime(13, 40));

    Notification::fake();
    $this->travelTo($this->dia->setTime(9, 50));
    resultadoDe($a, $this->propietario);
    $this->travelTo($this->dia->setTime(10, 40));
    resultadoDe($d, $this->propietario);

    $sugerencia = app(HorasEnDirecto::class)->sugerencia($this->torneo->refresh());
    expect($sugerencia)->not->toBeNull()
        ->and($sugerencia->desdePista)->toBe($dos->id)
        ->and($sugerencia->haciaPista)->toBe($uno->id);

    $this->actingAs($this->propietario);
    Livewire::test(HorariosDelTorneo::class, ['record' => $this->torneo->getRouteKey()])
        ->assertSee('En directo')
        ->assertSee('min tarde')
        ->call('aplicarSugerencia', $sugerencia->clave())
        ->assertNotified('Partido recolocado');

    $movido = Partido::query()->findOrFail($sugerencia->partido);
    expect($movido->pista_id)->toBe($uno->id)
        ->and($movido->franja->pista_id)->toBe($uno->id)
        ->and($movido->franja->inicio->getTimestamp())->toBeGreaterThanOrEqual($this->dia->setTime(11, 10)->getTimestamp());
    Notification::assertSentTo($movido->local->jugadores, CambioDePista::class);

    // Lo propuesto ya no vale: no se aplica dos veces.
    Livewire::test(HorariosDelTorneo::class, ['record' => $this->torneo->getRouteKey()])
        ->call('aplicarSugerencia', $sugerencia->clave())
        ->assertNotified('No se ha recolocado');
});
