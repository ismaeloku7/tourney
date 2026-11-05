<?php

use App\Actions\Competicion\GenerarPartidos;
use App\Actions\Competicion\RegistrarResultado;
use App\Actions\Competicion\SortearGrupos;
use App\Competicion\Datos\Resultado;
use App\Competicion\Datos\Set;
use App\Enums\EstadoFase;
use App\Enums\EstadoPartido;
use App\Enums\Rol;
use App\Filament\Resources\Torneos\Pages\CompeticionDelTorneo;
use App\Models\Categoria;
use App\Models\Fase;
use App\Models\FormatoPartido;
use App\Models\Inscripcion;
use App\Models\Organizador;
use App\Models\Participante;
use App\Models\Torneo;
use App\Models\User;
use Database\Seeders\PlanesSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * La página «Competición» de un torneo con dos categorías: una de 8
 * parejas en dos grupos de 4 con cuadro detrás, y otra vacía.
 */
beforeEach(function () {
    $this->seed(PlanesSeeder::class);
    usarOrganizador(Organizador::factory()->create());
    Filament::setCurrentPanel('panel');

    $this->propietario = User::factory()->rol(Rol::Propietario)->create();
    $this->actingAs($this->propietario);
    FormatoPartido::factory()->predeterminado()->create();

    $this->torneo = Torneo::factory()->enSorteo()->create(['nombre' => 'Open de Otoño']);
    $this->mixta = Categoria::factory()->create(['torneo_id' => $this->torneo->id, 'nombre' => 'Mixta B', 'orden' => 1]);
    $this->vacia = Categoria::factory()->create(['torneo_id' => $this->torneo->id, 'nombre' => 'Masculina A', 'orden' => 2]);
    for ($i = 1; $i <= 8; $i++) {
        Inscripcion::factory()->create(['participante_id' => Participante::factory()->create(['categoria_id' => $this->mixta->id, 'nombre' => "Pareja {$i}"])->id]);
    }

    $this->grupos = Fase::factory()->grupos(['tamano_de_grupo' => 4, 'semilla' => 3])->create(['categoria_id' => $this->mixta->id, 'nombre' => 'Fase de grupos']);
    $this->cuadro = Fase::factory()->eliminatoria()->create(['categoria_id' => $this->mixta->id, 'nombre' => 'Cuadro final']);
});

function competicionDe(Torneo $torneo): Testable
{
    return Livewire::test(CompeticionDelTorneo::class, ['record' => $torneo->getRouteKey()]);
}

test('enseña las categorías, los grupos con su clasificación y su cruce previsto, y el cuadro cuando se llena', function () {
    competicionDe($this->torneo)->assertOk()->assertSee('Mixta B')->assertSee('Masculina A')->assertSee('Sin sortear');

    app(SortearGrupos::class)->handle($this->grupos, $this->propietario);
    app(GenerarPartidos::class)->handle($this->grupos, $this->propietario);

    $pagina = competicionDe($this->torneo)
        ->assertSee('Grupo A')->assertSee('Grupo B')
        ->assertSee('0 de 12 partidos jugados')
        ->assertSee('Cruce previsto')->assertSee('vs 2º ')
        ->assertSee('El cuadro se llena al cerrar la fase anterior');

    $partido = $this->grupos->partidos()->orderBy('id')->firstOrFail();
    $pagina->assertActionVisible(TestAction::make('resultado')->arguments(['partido' => $partido->id]))
        ->assertActionHidden(TestAction::make('anular')->arguments(['partido' => $partido->id]))
        ->callAction(TestAction::make('resultado')->arguments(['partido' => $partido->id]), data: ['sets' => [['juegos_local' => 6, 'juegos_visitante' => 3], ['juegos_local' => 6, 'juegos_visitante' => 4]]])
        ->assertHasNoActionErrors()
        ->assertNotified('Resultado registrado')
        ->assertSee('1 de 12 partidos jugados')
        ->assertSee('6-3 6-4');

    expect($partido->refresh()->estado)->toBe(EstadoPartido::Jugado);

    // Cerrar la fase exige todos los resultados; con todos, llena el cuadro.
    $pagina->assertActionDisabled(TestAction::make('cerrarFase')->arguments(['fase' => $this->grupos->id]));

    foreach ($this->grupos->partidos()->whereIn('estado', ['pendiente', 'programado'])->orderBy('id')->get()->values() as $n => $p) {
        $sets = $n % 3 === 2 ? [[3, 6], [4, 6]] : [[6, 3], [6, 4]];
        app(RegistrarResultado::class)->handle($p, new Resultado(array_map(fn (array $s): Set => new Set($s[0], $s[1]), $sets)), $this->propietario);
    }

    competicionDe($this->torneo)
        ->assertActionEnabled(TestAction::make('cerrarFase')->arguments(['fase' => $this->grupos->id]))
        ->callAction(TestAction::make('cerrarFase')->arguments(['fase' => $this->grupos->id]), data: ['avisar' => false])
        ->assertNotified('Fase cerrada')
        ->assertSee('Semifinales')->assertSee('Final')
        ->assertDontSee('Cruce previsto');

    expect($this->grupos->refresh()->estado)->toBe(EstadoFase::Cerrada)
        ->and($this->cuadro->partidos()->count())->toBe(3);
});

test('la categoría elegida va en la dirección y una que no es del torneo no se abre', function () {
    competicionDe($this->torneo)->assertSet('categoriaId', $this->mixta->id)
        ->call('elegirCategoria', $this->vacia->id)->assertSet('categoriaId', $this->vacia->id)->assertSee('no tiene fases todavía');

    $ajena = Categoria::factory()->create(['torneo_id' => Torneo::factory()->create()->id]);
    competicionDe($this->torneo)->call('elegirCategoria', $ajena->id)->assertNotFound();
});

test('un partido de otro torneo no se puede tocar desde aquí', function () {
    $otro = Torneo::factory()->enSorteo()->create();
    $categoria = Categoria::factory()->create(['torneo_id' => $otro->id]);
    foreach (range(1, 4) as $i) {
        Inscripcion::factory()->create(['participante_id' => Participante::factory()->create(['categoria_id' => $categoria->id])->id]);
    }
    $fase = Fase::factory()->grupos(['semilla' => 1])->create(['categoria_id' => $categoria->id]);
    app(SortearGrupos::class)->handle($fase, $this->propietario);
    app(GenerarPartidos::class)->handle($fase, $this->propietario);
    $ajeno = $fase->partidos()->firstOrFail();

    competicionDe($this->torneo)->assertActionHidden(TestAction::make('resultado')->arguments(['partido' => $ajeno->id]));
    expect($ajeno->refresh()->estado)->toBe(EstadoPartido::Pendiente);
});

test('un administrador de otro torneo no entra', function () {
    $administrador = User::factory()->rol(Rol::Administrador)->create();
    $this->actingAs($administrador);

    // El recurso ya filtra sus torneos: el ajeno ni se encuentra.
    competicionDe($this->torneo)->assertNotFound();
});
