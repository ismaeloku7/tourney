<?php

use App\Enums\Rol;
use App\Filament\Resources\Categorias\Pages\EditCategoria;
use App\Filament\Resources\Categorias\RelationManagers\InscripcionesRelationManager;
use App\Filament\Resources\Jugadores\JugadorResource;
use App\Filament\Resources\Jugadores\Pages\EditJugador;
use App\Filament\Resources\Jugadores\RelationManagers\TorneosRelationManager;
use App\Filament\Resources\Parejas\Pages\EditPareja;
use App\Filament\Resources\Parejas\Pages\ListParejas;
use App\Filament\Resources\Parejas\ParejaResource;
use App\Filament\Resources\Torneos\Pages\PartidosDelTorneo;
use App\Filament\Resources\Torneos\TorneoResource;
use App\Models\Categoria;
use App\Models\Fase;
use App\Models\Inscripcion;
use App\Models\Jugador;
use App\Models\Organizador;
use App\Models\Participante;
use App\Models\Partido;
use App\Models\Torneo;
use App\Models\User;
use Database\Seeders\PlanesSeeder;
use Filament\Facades\Filament;
use Filament\Livewire\GlobalSearch;
use Livewire\Livewire;

/**
 * El panel cruzado: desde un jugador a sus torneos y parejas, desde las
 * parejas y los partidos a los jugadores, y todo por la búsqueda global.
 */
beforeEach(function () {
    $this->seed(PlanesSeeder::class);
    usarOrganizador(Organizador::factory()->create(['id' => 'norte']));
    Filament::setCurrentPanel('panel');
    $this->actingAs(User::factory()->rol(Rol::Propietario)->create());

    $this->rally = Torneo::factory()->create(['nombre' => 'Rally 2026']);
    $this->open = Torneo::factory()->create(['nombre' => 'Open de Otoño']);
    $this->iniciacion = Categoria::factory()->create(['torneo_id' => $this->rally->id, 'nombre' => 'Iniciación']);
    $mixta = Categoria::factory()->create(['torneo_id' => $this->open->id, 'nombre' => 'Mixta B']);

    $this->ana = Jugador::factory()->create(['nombre' => 'Ana', 'apellidos' => 'Zubiri', 'email' => 'ana@example.com', 'telefono' => '600111222']);
    $this->luis = Jugador::factory()->create(['nombre' => 'Luis', 'apellidos' => 'Ortega']);
    $this->delRally = Inscripcion::factory()->create(['participante_id' => Participante::factory()->conJugadores($this->ana, $this->luis)->create(['categoria_id' => $this->iniciacion->id, 'nombre' => 'Zubiri / Ortega'])->id]);
    $this->delOpen = Inscripcion::factory()->create(['participante_id' => Participante::factory()->conJugadores($this->ana, Jugador::factory()->create())->create(['categoria_id' => $mixta->id])->id]);
    $this->otra = Inscripcion::factory()->create(['participante_id' => Participante::factory()->create(['categoria_id' => $this->iniciacion->id, 'nombre' => 'Pérez / Ruiz'])->id]);
});

test('la ficha del jugador lista sus torneos con la pareja, el compañero y el estado, y salta al torneo y a la pareja', function () {
    Livewire::test(TorneosRelationManager::class, ['ownerRecord' => $this->ana, 'pageClass' => EditJugador::class])
        ->assertCanSeeTableRecords([$this->delRally->participante, $this->delOpen->participante])
        ->assertCanNotSeeTableRecords([$this->otra->participante])
        ->assertSee('Rally 2026')
        ->assertSee('Open de Otoño')
        ->assertSee('Luis Ortega')
        ->assertTableActionHasUrl('torneo', TorneoResource::getUrl('view', ['record' => $this->rally]), $this->delRally->participante)
        ->assertTableActionHasUrl('pareja', ParejaResource::getUrl('edit', ['record' => $this->delRally]), $this->delRally->participante);

    expect(TorneosRelationManager::getBadge($this->ana, EditJugador::class))->toBe('2');
});

test('en las listas de parejas y de inscritos cada jugador enlaza a su ficha', function () {
    $fichaDeAna = JugadorResource::getUrl('edit', ['record' => $this->ana]);

    Livewire::test(ListParejas::class)->assertSeeHtml($fichaDeAna)->assertSee('Luis Ortega');
    Livewire::test(InscripcionesRelationManager::class, ['ownerRecord' => $this->iniciacion, 'pageClass' => EditCategoria::class])->assertSeeHtml($fichaDeAna);
});

test('desde la pareja se salta al torneo y a las fichas de sus jugadores', function () {
    Livewire::test(EditPareja::class, ['record' => $this->delRally->getRouteKey()])
        ->assertActionHasUrl('torneo', TorneoResource::getUrl('view', ['record' => $this->rally]))
        ->assertActionHasUrl("jugador_{$this->ana->id}", JugadorResource::getUrl('edit', ['record' => $this->ana]));
});

test('en los partidos del torneo las parejas enlazan a su ficha y los huecos vacíos no', function () {
    $fase = Fase::factory()->eliminatoria()->create(['categoria_id' => $this->iniciacion->id]);
    Partido::factory()->create(['fase_id' => $fase->id, 'ronda' => 1, 'posicion' => 1, 'local_id' => $this->delRally->participante_id, 'visitante_id' => null, 'origen_visitante' => ['tipo' => 'clasificado', 'referencia' => 2, 'posicion' => null]]);

    Livewire::test(PartidosDelTorneo::class, ['record' => $this->rally->getRouteKey()])
        ->assertSeeHtml(ParejaResource::getUrl('edit', ['record' => $this->delRally]))
        ->assertSee('Zubiri / Ortega')
        ->assertSee('Clasificado 2');
});

test('la búsqueda global encuentra jugadores por apellido, correo o teléfono, y parejas por jugador', function () {
    Livewire::test(GlobalSearch::class)->set('search', 'Zubiri')->assertSee('Ana Zubiri')->assertSee('Zubiri / Ortega')->assertSee('Rally 2026 · Iniciación');
    Livewire::test(GlobalSearch::class)->set('search', '600111222')->assertSee('Ana Zubiri');
    Livewire::test(GlobalSearch::class)->set('search', 'ana@example.com')->assertSee('Ana Zubiri');
    Livewire::test(GlobalSearch::class)->set('search', 'Ortega')->assertSee('Zubiri / Ortega')->assertDontSee('Pérez / Ruiz');
});
