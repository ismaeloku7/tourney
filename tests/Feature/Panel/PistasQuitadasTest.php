<?php

use App\Actions\Competicion\PlanificarHorarios;
use App\Actions\Competicion\SoltarPartidosDePista;
use App\Enums\EstadoPartido;
use App\Enums\EstadoTorneo;
use App\Enums\Rol;
use App\Filament\Resources\Torneos\Pages\EditTorneo;
use App\Filament\Resources\Torneos\Pages\HorariosDelTorneo;
use App\Filament\Resources\Torneos\RelationManagers\PistasRelationManager;
use App\Models\Categoria;
use App\Models\Fase;
use App\Models\FormatoPartido;
use App\Models\Franja;
use App\Models\Organizador;
use App\Models\Partido;
use App\Models\Pista;
use App\Models\Torneo;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\PlanesSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

/**
 * Una pista que deja de ser del torneo con partidos ya colocados: lo
 * que no se ha jugado vuelve a quedar sin hora, y lo que se quedó
 * colgado de antes se ve y se suelta desde Horarios.
 */
beforeEach(function () {
    $this->seed(PlanesSeeder::class);
    usarOrganizador(Organizador::factory()->create());
    Filament::setCurrentPanel('panel');
    FormatoPartido::factory()->predeterminado()->create(['duracion_estimada' => 60]);
    $this->propietario = User::factory()->rol(Rol::Propietario)->create();
    $this->actingAs($this->propietario);

    $this->torneo = Torneo::factory()->create(['estado' => EstadoTorneo::Sorteo, 'descanso_minimo_minutos' => 0, 'margen_entre_partidos_minutos' => 10]);
    $this->fase = Fase::factory()->create(['categoria_id' => Categoria::factory()->create(['torneo_id' => $this->torneo->id])->id]);
    $this->dia = CarbonImmutable::parse($this->torneo->fecha_inicio->toDateString());
    [$this->uno, $this->dos] = Pista::factory()->count(2)->create()->all();
    foreach ([$this->uno, $this->dos] as $pista) {
        $this->torneo->torneoPistas()->create(['pista_id' => $pista->id, 'orden' => $pista->id, 'disponibilidad' => [['fecha' => $this->dia->toDateString(), 'desde' => '09:00', 'hasta' => '21:00']]]);
    }
});

function puestoEn(Fase $fase, Pista $pista, CarbonImmutable $inicio, string $estado = 'programado'): Partido
{
    $partido = Partido::factory()->create(['fase_id' => $fase->id, 'estado' => $estado]);
    $franja = $fase->categoria->torneo->franjas()->create(['pista_id' => $pista->id, 'inicio' => $inicio, 'fin' => $inicio->addMinutes(70), 'inicio_previsto' => $inicio]);
    $partido->update(['franja_id' => $franja->id, 'pista_id' => $pista->id]);

    return $partido->refresh();
}

test('quitar una pista del torneo suelta sus partidos sin jugar y conserva los jugados', function () {
    $sinJugar = puestoEn($this->fase, $this->dos, $this->dia->setTime(9, 0));
    $jugado = puestoEn($this->fase, $this->dos, $this->dia->setTime(10, 10), 'jugado');
    $enLaOtra = puestoEn($this->fase, $this->uno, $this->dia->setTime(9, 0));
    $torneoPista = $this->torneo->torneoPistas()->where('pista_id', $this->dos->id)->firstOrFail();

    Livewire::test(PistasRelationManager::class, ['ownerRecord' => $this->torneo, 'pageClass' => EditTorneo::class])
        ->callAction(TestAction::make('delete')->table($torneoPista))
        ->assertNotified('Un partido se ha quedado sin hora');

    expect($this->torneo->torneoPistas()->count())->toBe(1)
        ->and($sinJugar->refresh())->estado->toBe(EstadoPartido::Pendiente)->franja_id->toBeNull()->pista_id->toBeNull()
        ->and($jugado->refresh()->pista_id)->toBe($this->dos->id)
        ->and($enLaOtra->refresh()->franja_id)->not->toBeNull()
        ->and(Franja::query()->where('pista_id', $this->dos->id)->count())->toBe(1);

    // Horarios avisa de que hay uno sin hora, y «Calcular» lo coloca en la pista que queda.
    Livewire::test(HorariosDelTorneo::class, ['record' => $this->torneo->getRouteKey()])
        ->assertSee('1 partido sin hora ni pista')
        ->assertActionHidden('soltarHuerfanos');

    app(PlanificarHorarios::class)->handle($this->torneo->refresh(), $this->propietario);
    expect($sinJugar->refresh()->pista_id)->toBe($this->uno->id)->and($sinJugar->estado)->toBe(EstadoPartido::Programado);
});

test('los partidos que se quedaron colgados en una pista quitada antes se ven en Horarios y se sueltan desde ahí', function () {
    $colgado = puestoEn($this->fase, $this->dos, $this->dia->setTime(9, 0));
    $otroColgado = puestoEn($this->fase, $this->dos, $this->dia->setTime(10, 10), 'pendiente');
    puestoEn($this->fase, $this->uno, $this->dia->setTime(9, 0));
    // La pista se quitó sin soltar nada (como pasaba antes).
    $this->torneo->torneoPistas()->where('pista_id', $this->dos->id)->delete();

    expect(SoltarPartidosDePista::enPistasQuitadas($this->torneo)->pluck('id')->sort()->values()->all())->toBe([$colgado->id, $otroColgado->id]);

    Livewire::test(HorariosDelTorneo::class, ['record' => $this->torneo->getRouteKey()])
        ->assertSee('2 partidos siguen colocados en pistas que ya no son del torneo')
        ->assertActionVisible('soltarHuerfanos')
        ->callAction('soltarHuerfanos')
        ->assertNotified('2 partidos sin hora')
        ->assertActionHidden('soltarHuerfanos')
        ->assertSee('2 partidos sin hora ni pista');

    expect($colgado->refresh()->franja_id)->toBeNull()
        ->and($otroColgado->refresh()->pista_id)->toBeNull()
        ->and(Franja::query()->where('pista_id', $this->dos->id)->count())->toBe(0)
        ->and(SoltarPartidosDePista::enPistasQuitadas($this->torneo))->toHaveCount(0);
});
