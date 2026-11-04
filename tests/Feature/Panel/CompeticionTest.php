<?php

use App\Actions\Competicion\GenerarPartidos;
use App\Actions\Competicion\RegistrarResultado;
use App\Actions\Competicion\SortearGrupos;
use App\Competicion\Datos\Resultado;
use App\Competicion\Datos\Set;
use App\Enums\EstadoFase;
use App\Enums\EstadoInscripcion;
use App\Enums\EstadoPartido;
use App\Enums\EstadoTorneo;
use App\Enums\ModoDeSorteo;
use App\Enums\Rol;
use App\Filament\Competicion\Marcador;
use App\Filament\Competicion\SorteoDeGrupos;
use App\Filament\Resources\Categorias\Pages\EditCategoria;
use App\Filament\Resources\Categorias\RelationManagers\FasesRelationManager;
use App\Filament\Resources\Torneos\Pages\EditTorneo;
use App\Filament\Resources\Torneos\Pages\PartidosDelTorneo;
use App\Models\Categoria;
use App\Models\Fase;
use App\Models\FormatoPartido;
use App\Models\Franja;
use App\Models\Inscripcion;
use App\Models\Jugador;
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

test('al sortear se fijan a mano parejas en sus grupos y el resto sale por sorteo', function () {
    $ids = $this->categoria->participantes()->orderBy('id')->pluck('id')->all();
    [$sinCabeza1, $sinCabeza2, $sinCabeza3] = array_slice($ids, 9, 3);

    fasesDe($this->categoria)
        ->mountAction(TestAction::make('sortear')->table($this->grupos))
        ->setActionData(['grupo_0' => [(string) $sinCabeza1, (string) $sinCabeza2], 'grupo_2' => [(string) $sinCabeza3]])
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertNotified('3 grupos y 18 partidos');

    $grupos = $this->grupos->refresh()->grupos()->with('participantes')->orderBy('orden')->get();
    expect($grupos)->toHaveCount(3)
        ->and($grupos[0]->participantes->pluck('id')->all())->toContain($sinCabeza1, $sinCabeza2)
        ->and($grupos[2]->participantes->pluck('id')->all())->toContain($sinCabeza3)
        ->and($grupos->map(fn ($g): int => $g->participantes->count())->all())->toBe([4, 4, 4])
        ->and($this->grupos->configuracionDeGrupos()->gruposManuales)->toBe([[$sinCabeza1, $sinCabeza2], [], [$sinCabeza3]]);

    // Al volver a abrirlo salen los grupos tal y como quedaron.
    fasesDe($this->categoria)
        ->mountAction(TestAction::make('sortear')->table($this->grupos))
        ->assertActionDataSet(['grupo_0' => $grupos[0]->participantes->map(fn ($p): string => (string) $p->id)->all()]);

    // Más parejas de las que caben en un grupo y huecos por sortear: no se sortea.
    fasesDe($this->categoria)
        ->callAction(TestAction::make('sortear')->table($this->grupos), ['grupo_0' => [], 'grupo_1' => array_map('strval', array_slice($ids, 0, 5)), 'grupo_2' => []])
        ->assertNotified('No se ha podido sortear');
});

test('cada fase se oculta o se enseña en la web desde su listado', function () {
    expect($this->grupos->visible_en_web)->toBeTrue();

    fasesDe($this->categoria)->call('updateTableColumnState', 'visible_en_web', (string) $this->grupos->getKey(), false);

    expect($this->grupos->refresh()->visible_en_web)->toBeFalse()
        ->and($this->cuadro->refresh()->visible_en_web)->toBeTrue();
});

test('en el sorteo se ponen las cabezas de serie en orden y entran las pendientes que se elijan', function () {
    $ids = $this->categoria->participantes()->orderBy('id')->pluck('id')->all();
    $pendiente = Participante::factory()->create(['categoria_id' => $this->categoria->id]);
    Inscripcion::factory()->pendiente()->create(['participante_id' => $pendiente->id]);
    $otraPendiente = Participante::factory()->create(['categoria_id' => $this->categoria->id]);
    Inscripcion::factory()->pendiente()->create(['participante_id' => $otraPendiente->id]);

    fasesDe($this->categoria)
        ->callAction(TestAction::make('sortear')->table($this->grupos), [
            'modo' => 'cabezas_de_serie',
            'cabezas' => [(string) $ids[5], (string) $ids[4], (string) $ids[0]],
            'grupo_1' => [(string) $pendiente->id],
        ])
        ->assertHasNoActionErrors()
        ->assertNotified('3 grupos y 22 partidos');

    $cabezas = Participante::query()->where('categoria_id', $this->categoria->id)->whereNotNull('cabeza_de_serie')->orderBy('cabeza_de_serie')->pluck('id')->all();
    expect($cabezas)->toBe([$ids[5], $ids[4], $ids[0]])
        ->and($pendiente->inscripcion->refresh()->estado)->toBe(EstadoInscripcion::Confirmada)
        ->and($otraPendiente->inscripcion->refresh()->estado)->toBe(EstadoInscripcion::Pendiente);

    $grupos = $this->grupos->refresh()->grupos()->with('participantes')->get();
    expect($grupos[1]->participantes->pluck('id')->all())->toContain($pendiente->id)
        ->and($grupos->flatMap(fn ($g) => $g->participantes->pluck('id'))->all())->not->toContain($otraPendiente->id)
        ->and($grupos->map(fn ($g): int => count(array_intersect($g->participantes->pluck('id')->all(), $cabezas)))->all())->toBe([1, 1, 1]);
});

test('en el sorteo se pueden hacer todos los grupos a mano', function () {
    $ids = array_map('strval', $this->categoria->participantes()->orderBy('id')->pluck('id')->all());

    fasesDe($this->categoria)
        ->callAction(TestAction::make('sortear')->table($this->grupos), [
            'modo' => 'manual',
            'grupo_0' => array_slice($ids, 0, 4),
            'grupo_1' => array_slice($ids, 4, 4),
            'grupo_2' => array_slice($ids, 8, 4),
        ])
        ->assertHasNoActionErrors()
        ->assertNotified('Grupos guardados');

    $grupos = $this->grupos->refresh()->grupos()->with('participantes')->get();
    expect($grupos->map(fn ($g): array => $g->participantes->pluck('id')->sort()->values()->all())->all())
        ->toBe(array_map(fn (array $trozo): array => array_map('intval', $trozo), array_chunk($ids, 4)))
        ->and($this->grupos->configuracionDeGrupos()->modoDeSorteo)->toBe(ModoDeSorteo::Manual);

    expect($this->grupos->partidos()->count())->toBe(18)
        ->and($this->grupos->estado)->toBe(EstadoFase::Preparada);

    // Una pareja colocada ya no se puede elegir en otro grupo.
    fasesDe($this->categoria)
        ->callAction(TestAction::make('sortear')->table($this->grupos), ['grupo_1' => [$ids[0], ...array_slice($ids, 5, 3)]]);
    expect($this->grupos->grupos()->first()->participantes()->pluck('participantes.id')->all())->toContain((int) $ids[0]);
});

test('en los buscadores del sorteo salen los jugadores con su nivel', function () {
    $pareja = Participante::factory()
        ->conJugadores(Jugador::factory()->create(['nombre' => 'Ana', 'apellidos' => 'Gil', 'nivel_declarado' => '3.5']), Jugador::factory()->create(['nombre' => 'Eva', 'apellidos' => 'Sanz', 'nivel_declarado' => null]))
        ->create(['categoria_id' => $this->categoria->id, 'cabeza_de_serie' => 9]);
    Inscripcion::factory()->pendiente()->create(['participante_id' => $pareja->id]);

    expect(SorteoDeGrupos::etiqueta($pareja->refresh()))
        ->toContain('Ana Gil (3,5)')
        ->toContain('Eva Sanz (sin nivel)')
        ->toContain('CS 9')
        ->toContain('pendiente');
});

test('después del sorteo se cambian los grupos a mano y solo se rehacen los que cambian', function () {
    app(SortearGrupos::class)->handle($this->grupos, $this->propietario);
    app(GenerarPartidos::class)->handle($this->grupos, $this->propietario);
    $antes = $this->grupos->grupos()->with('participantes')->get();
    $deA = $antes[0]->partidos()->firstOrFail();
    app(RegistrarResultado::class)->handle($deA, new Resultado([new Set(6, 1), new Set(6, 1)]), $this->propietario);

    $b = $antes[1]->participantes->pluck('id')->all();
    $c = $antes[2]->participantes->pluck('id')->all();
    $partidosDeB = $antes[1]->partidos()->pluck('id')->all();

    // Se cambia una pareja de B por una de C.
    fasesDe($this->categoria)
        ->callAction(TestAction::make('sortear')->table($this->grupos), [
            'grupo_1' => array_map('strval', [$c[0], ...array_slice($b, 1)]),
            'grupo_2' => array_map('strval', [$b[0], ...array_slice($c, 1)]),
        ])
        ->assertHasNoActionErrors()
        ->assertNotified('Grupos guardados');

    $despues = $this->grupos->refresh()->grupos()->with('participantes')->get();
    expect($despues[1]->participantes->pluck('id')->all())->toContain($c[0])->not->toContain($b[0])
        ->and($despues[2]->participantes->pluck('id')->all())->toContain($b[0])
        ->and($deA->refresh()->estado)->toBe(EstadoPartido::Jugado)
        ->and($despues[1]->partidos()->pluck('id')->intersect($partidosDeB)->all())->toBe([])
        ->and($this->grupos->partidos()->count())->toBe(18);

    // Sin tocar nada, no cambia nada.
    fasesDe($this->categoria)->callAction(TestAction::make('sortear')->table($this->grupos))
        ->assertNotified('Los grupos no cambian');
    expect($deA->refresh()->estado)->toBe(EstadoPartido::Jugado);
});

test('con grupos hechos, una pareja confirmada después entra en un hueco sin rehacer los demás', function () {
    app(SortearGrupos::class)->handle($this->grupos, $this->propietario);
    app(GenerarPartidos::class)->handle($this->grupos, $this->propietario);
    $partidosAntes = $this->grupos->partidos()->pluck('id')->all();

    $nueva = Participante::factory()->create(['categoria_id' => $this->categoria->id]);
    Inscripcion::factory()->create(['participante_id' => $nueva->id]);

    fasesDe($this->categoria)
        ->callAction(TestAction::make('sortear')->table($this->grupos))
        ->assertHasNoActionErrors()
        ->assertNotified('Grupos guardados');

    $grupos = $this->grupos->refresh()->grupos()->with('participantes')->get();
    $suyo = $grupos->first(fn ($g): bool => $g->participantes->contains('id', $nueva->id));
    expect($suyo)->not->toBeNull()
        ->and($grupos->sum(fn ($g): int => $g->participantes->count()))->toBe(13)
        // Los partidos de los otros dos grupos siguen siendo los mismos.
        ->and($this->grupos->partidos()->where('grupo_id', '!=', $suyo->id)->pluck('id')->diff($partidosAntes)->all())->toBe([]);
});
