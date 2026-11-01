<?php

use App\Actions\Competicion\GenerarPartidos;
use App\Actions\Competicion\RegistrarResultado;
use App\Actions\Competicion\SortearGrupos;
use App\Competicion\Datos\Resultado;
use App\Competicion\Datos\Set;
use App\Enums\EstadoFase;
use App\Enums\EstadoPartido;
use App\Enums\EstadoTorneo;
use App\Enums\Rol;
use App\Filament\Competicion\Marcador;
use App\Filament\Resources\Categorias\Pages\EditCategoria;
use App\Filament\Resources\Categorias\RelationManagers\FasesRelationManager;
use App\Filament\Resources\Torneos\Pages\EditTorneo;
use App\Filament\Resources\Torneos\Pages\PartidosDelTorneo;
use App\Models\Categoria;
use App\Models\Fase;
use App\Models\FormatoPartido;
use App\Models\Franja;
use App\Models\Inscripcion;
use App\Models\Organizador;
use App\Models\Participante;
use App\Models\Pista;
use App\Models\Torneo;
use App\Models\User;
use Database\Seeders\PlanesSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * Un torneo en sorteo con una categoría de 12 parejas confirmadas, fase
 * de grupos de 4 y cuadro, y 4 pistas dos días de 9 a 21.
 */
beforeEach(function () {
    $this->seed(PlanesSeeder::class);
    usarOrganizador(Organizador::factory()->create());
    Filament::setCurrentPanel('panel');

    $this->propietario = User::factory()->rol(Rol::Propietario)->create();
    $this->actingAs($this->propietario);
    FormatoPartido::factory()->predeterminado()->create(['duracion_estimada' => 60]);

    $this->torneo = Torneo::factory()->enSorteo()->create(['descanso_minimo_minutos' => 30]);
    foreach (Pista::factory()->count(4)->create() as $pista) {
        $this->torneo->torneoPistas()->create(['pista_id' => $pista->id, 'orden' => $pista->orden, 'disponibilidad' => [
            ['fecha' => $this->torneo->fecha_inicio->toDateString(), 'desde' => '09:00', 'hasta' => '21:00'],
            ['fecha' => $this->torneo->fecha_fin->toDateString(), 'desde' => '09:00', 'hasta' => '21:00'],
        ]]);
    }

    $this->categoria = Categoria::factory()->create(['torneo_id' => $this->torneo->id, 'cupo' => 16]);
    for ($i = 1; $i <= 12; $i++) {
        $pareja = Participante::factory()->create(['categoria_id' => $this->categoria->id, 'cabeza_de_serie' => $i <= 3 ? $i : null]);
        Inscripcion::factory()->create(['participante_id' => $pareja->id]);
    }

    $this->grupos = Fase::factory()->grupos(['tamano_de_grupo' => 4, 'modo_de_sorteo' => 'cabezas_de_serie', 'semilla' => 7, 'clasificados' => ['por_grupo' => 2, 'mejores_de_posicion' => 3, 'cuantos_mejores' => 2]])->create(['categoria_id' => $this->categoria->id]);
    $this->cuadro = Fase::factory()->eliminatoria()->create(['categoria_id' => $this->categoria->id]);
});

function fasesDe(Categoria $categoria): Testable
{
    return Livewire::test(FasesRelationManager::class, ['ownerRecord' => $categoria, 'pageClass' => EditCategoria::class]);
}

function mesaDe(Torneo $torneo): Testable
{
    return Livewire::test(PartidosDelTorneo::class, ['record' => $torneo->getRouteKey()]);
}

function fichaDe(Torneo $torneo): Testable
{
    return Livewire::test(EditTorneo::class, ['record' => $torneo->getRouteKey()]);
}

function jugarTodosLosPartidosDe(Fase $fase, User $usuario): void
{
    foreach ($fase->partidos()->whereNotNull('local_id')->whereNotNull('visitante_id')->whereIn('estado', ['pendiente', 'programado'])->orderBy('id')->get()->values() as $n => $partido) {
        $sets = $n % 3 === 2 ? [[3, 6], [4, 6]] : [[6, 3], [6, 4]];
        app(RegistrarResultado::class)->handle($partido, new Resultado(array_map(fn (array $s): Set => new Set($s[0], $s[1]), $sets)), $usuario);
    }
}

test('desde las fases se sortea, se mira la clasificación y se cierra', function () {
    $componente = fasesDe($this->categoria);

    $componente->assertActionVisible(TestAction::make('sortear')->table($this->grupos))
        ->assertActionHidden(TestAction::make('sortear')->table($this->cuadro))
        ->assertActionHidden(TestAction::make('clasificacion')->table($this->grupos));

    $componente->callAction(TestAction::make('sortear')->table($this->grupos))
        ->assertHasNoActionErrors()
        ->assertNotified('3 grupos y 18 partidos');

    expect($this->grupos->refresh()->estado)->toBe(EstadoFase::Preparada)
        ->and($this->grupos->grupos()->count())->toBe(3)
        ->and($this->grupos->partidos()->count())->toBe(18);

    // Con la fase sin jugar, cerrar no puede ser; la clasificación ya se puede mirar.
    // (Tras una acción rechazada su diálogo se queda abierto: componente nuevo para la siguiente.)
    $componente->callAction(TestAction::make('cerrar')->table($this->grupos))->assertNotified('No se ha podido cerrar la fase');
    fasesDe($this->categoria)->assertActionVisible(TestAction::make('clasificacion')->table($this->grupos));
    expect(view('filament.panel.competicion.clasificacion', ['fase' => $this->grupos->refresh()])->render())->toContain('Grupo A')->toContain('Pts');

    jugarTodosLosPartidosDe($this->grupos, $this->propietario);

    fasesDe($this->categoria)->callAction(TestAction::make('cerrar')->table($this->grupos))
        ->assertHasNoActionErrors()
        ->assertNotified('Fase cerrada');

    expect($this->grupos->refresh()->estado)->toBe(EstadoFase::Cerrada)
        ->and($this->cuadro->refresh()->estado)->toBe(EstadoFase::Preparada)
        ->and($this->cuadro->partidos()->count())->toBe(7);

    fasesDe($this->categoria)->assertActionVisible(TestAction::make('cuadro')->table($this->cuadro));
    expect(view('filament.panel.competicion.cuadro', ['fase' => $this->cuadro])->render())->toContain('Cuartos de final')->toContain('Final');
});

test('la mesa de partidos registra, rechaza, anula y programa', function () {
    app(SortearGrupos::class)->handle($this->grupos, $this->propietario);
    app(GenerarPartidos::class)->handle($this->grupos, $this->propietario);
    $partido = $this->grupos->partidos()->orderBy('id')->firstOrFail();

    mesaDe($this->torneo)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$partido])
        ->assertSee($partido->local->nombre);

    // Un 7-3 no existe en el formato.
    mesaDe($this->torneo)->callAction(TestAction::make('resultado')->table($partido), ['sets' => [['juegos_local' => 7, 'juegos_visitante' => 3], ['juegos_local' => 6, 'juegos_visitante' => 4]]])
        ->assertNotified('El resultado no cuadra con el formato');
    expect($partido->refresh()->estado)->toBe(EstadoPartido::Pendiente);

    $mesa = mesaDe($this->torneo);
    $mesa->callAction(TestAction::make('resultado')->table($partido), ['sets' => [['juegos_local' => 6, 'juegos_visitante' => 3], ['juegos_local' => 7, 'juegos_visitante' => 6, 'tie_break_local' => 7, 'tie_break_visitante' => 5], ['juegos_local' => null, 'juegos_visitante' => null]]])
        ->assertHasNoActionErrors()
        ->assertNotified('Resultado registrado');

    expect($partido->refresh()->estado)->toBe(EstadoPartido::Jugado)
        ->and($partido->ganador_id)->toBe($partido->local_id)
        ->and($partido->sets()->count())->toBe(2)
        ->and(Marcador::resultado($partido))->toBe('6-3 7-6(5)');

    $mesa->callAction(TestAction::make('anular')->table($partido))->assertHasNoActionErrors()->assertNotified('Resultado anulado');
    expect($partido->refresh()->estado)->toBe(EstadoPartido::Pendiente)->and($partido->sets()->count())->toBe(0);

    // Un walkover a favor del visitante.
    $mesa->callAction(TestAction::make('resultado')->table($partido), ['especial' => 'walkover', 'gana_local' => '0'])->assertHasNoActionErrors();
    expect($partido->refresh()->estado)->toBe(EstadoPartido::Walkover)->and($partido->ganador_id)->toBe($partido->visitante_id);

    // Programar a mano en una franja libre.
    $otro = $this->grupos->partidos()->where('estado', EstadoPartido::Pendiente->value)->orderBy('id')->firstOrFail();
    $franja = Franja::query()->create(['torneo_id' => $this->torneo->id, 'pista_id' => $this->torneo->torneoPistas()->first()->pista_id, 'inicio' => $this->torneo->fecha_inicio->setTime(10, 0), 'fin' => $this->torneo->fecha_inicio->setTime(11, 0)]);

    $mesa->callAction(TestAction::make('programar')->table($otro), ['franja_id' => $franja->id])->assertHasNoActionErrors()->assertNotified('Partido programado');
    expect($otro->refresh()->estado)->toBe(EstadoPartido::Programado)->and($otro->pista_id)->toBe($franja->pista_id);

    $mesa->callAction(TestAction::make('programar')->table($otro), ['franja_id' => null])->assertHasNoActionErrors();
    expect($otro->refresh()->franja_id)->toBeNull()->and($otro->estado)->toBe(EstadoPartido::Pendiente);
});

test('desde la ficha del torneo se planifica y se cambia el estado con sus comprobaciones', function () {
    app(SortearGrupos::class)->handle($this->grupos, $this->propietario);
    app(GenerarPartidos::class)->handle($this->grupos, $this->propietario);

    // Sin horarios no se pasa a en juego.
    fichaDe($this->torneo)->assertSuccessful()->callAction('estado_siguiente')->assertNotified('No se ha podido cambiar el estado');
    expect($this->torneo->refresh()->estado)->toBe(EstadoTorneo::Sorteo);

    $ficha = fichaDe($this->torneo);
    $ficha->callAction('planificar')->assertHasNoActionErrors()->assertNotified('18 partidos con hora y pista');
    expect($this->grupos->partidos()->whereNotNull('franja_id')->count())->toBe(18)
        ->and($this->torneo->franjas()->count())->toBe(4 * 2 * 12);

    $ficha->callAction('estado_siguiente')->assertHasNoActionErrors()->assertNotified('El torneo está en «En juego»');
    expect($this->torneo->refresh()->estado)->toBe(EstadoTorneo::EnJuego)->and($this->torneo->horarios_publicados_en)->not->toBeNull();

    $ficha->callAction('estado_anterior')->assertHasNoActionErrors();
    expect($this->torneo->refresh()->estado)->toBe(EstadoTorneo::Sorteo);

    // Un torneo sin categorías no se publica.
    $vacio = Torneo::factory()->create();
    Livewire::test(EditTorneo::class, ['record' => $vacio->getRouteKey()])
        ->callAction('estado_siguiente')
        ->assertNotified('No se ha podido cambiar el estado');
    expect($vacio->refresh()->estado)->toBe(EstadoTorneo::Borrador);
});
