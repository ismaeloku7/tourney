<?php

use App\Actions\Torneos\DuplicarCategoria;
use App\Enums\EstadoFase;
use App\Enums\Genero;
use App\Enums\Rol;
use App\Enums\TipoDeFase;
use App\Filament\Resources\Torneos\Pages\EditTorneo;
use App\Filament\Resources\Torneos\RelationManagers\CategoriasRelationManager;
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
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PlanesSeeder::class);
    usarOrganizador(Organizador::factory()->create());
    Filament::setCurrentPanel('panel');

    $this->propietario = User::factory()->rol(Rol::Propietario)->create();
    $this->torneo = Torneo::factory()->create();
    $formato = FormatoPartido::factory()->create(['nombre' => 'Super tie-break']);

    // Una categoría ya en marcha: grupos sorteados a mano, cuadro y consolación, y parejas.
    $this->origen = Categoria::factory()->create(['torneo_id' => $this->torneo->id, 'nombre' => 'Masculina 3ª', 'genero' => Genero::Masculino, 'cupo' => 16, 'precio' => '30.00', 'formato_partido_id' => $formato->id, 'orden' => 1]);
    $this->grupos = Fase::factory()->grupos(['tamano_de_grupo' => 4, 'semilla' => 99, 'grupos_manuales' => [[1, 2], [3, 4]]])->create(['categoria_id' => $this->origen->id, 'estado' => EstadoFase::EnJuego, 'semilla_sorteo' => 99]);
    $this->cuadro = Fase::factory()->eliminatoria(['tamano_de_cuadro' => 8, 'tercer_y_cuarto_puesto' => true])->create(['categoria_id' => $this->origen->id, 'fase_origen_id' => $this->grupos->id]);
    Fase::factory()->consolacion()->create(['categoria_id' => $this->origen->id, 'orden' => 3, 'fase_origen_id' => $this->grupos->id]);
    foreach (range(1, 3) as $i) {
        Inscripcion::factory()->create(['participante_id' => Participante::factory()->create(['categoria_id' => $this->origen->id])->id]);
    }
});

test('duplicar copia los datos y las fases, sin parejas ni sorteos, y enlaza las fases entre sí', function () {
    $nueva = app(DuplicarCategoria::class)->handle($this->origen, ['nombre' => 'Femenina 3ª', 'genero' => Genero::Femenino], usuario: $this->propietario);

    expect($nueva->id)->not->toBe($this->origen->id)
        ->and($nueva->torneo_id)->toBe($this->torneo->id)
        ->and($nueva->nombre)->toBe('Femenina 3ª')
        ->and($nueva->genero)->toBe(Genero::Femenino)
        ->and($nueva->cupo)->toBe(16)
        ->and($nueva->precio)->toBe('30.00')
        ->and($nueva->formato_partido_id)->toBe($this->origen->formato_partido_id)
        ->and($nueva->orden)->toBe(2)
        ->and($nueva->participantes()->count())->toBe(0)
        ->and($nueva->inscripciones()->count())->toBe(0);

    $fases = $nueva->fases()->get();
    expect($fases->pluck('tipo')->all())->toBe([TipoDeFase::Grupos, TipoDeFase::Eliminatoria, TipoDeFase::Consolacion])
        ->and($fases->every(fn (Fase $f): bool => $f->estado === EstadoFase::Pendiente && $f->semilla_sorteo === null))->toBeTrue();

    [$grupos, $cuadro, $consolacion] = $fases->all();
    expect($grupos->configuracionDeGrupos()->tamanoDeGrupo)->toBe(4)
        ->and($grupos->configuracionDeGrupos()->semilla)->toBeNull()
        ->and($grupos->configuracionDeGrupos()->gruposManuales)->toBeNull()
        ->and($cuadro->configuracionDeEliminatoria()->tamanoDeCuadro)->toBe(8)
        ->and($cuadro->configuracionDeEliminatoria()->tercerYCuartoPuesto)->toBeTrue()
        ->and($cuadro->fase_origen_id)->toBe($grupos->id)
        ->and($consolacion->fase_origen_id)->toBe($grupos->id);

    // La original sigue igual.
    expect($this->origen->fases()->count())->toBe(3)
        ->and($this->origen->participantes()->count())->toBe(3)
        ->and($this->grupos->refresh()->estado)->toBe(EstadoFase::EnJuego);
});

test('desde la tabla de categorías del torneo se duplica con nombre nuevo y, si se quiere, sin fases', function () {
    $this->actingAs($this->propietario);
    $tabla = Livewire::test(CategoriasRelationManager::class, ['ownerRecord' => $this->torneo, 'pageClass' => EditTorneo::class]);

    // Sin tocar nada: «(copia)», mismo género y con fases.
    $tabla->callAction(TestAction::make('duplicar')->table($this->origen))
        ->assertHasNoActionErrors()
        ->assertNotified('Categoría duplicada');

    $copia = Categoria::query()->where('nombre', 'Masculina 3ª (copia)')->sole();
    expect($copia->genero)->toBe(Genero::Masculino)->and($copia->fases()->count())->toBe(3);

    Livewire::test(CategoriasRelationManager::class, ['ownerRecord' => $this->torneo, 'pageClass' => EditTorneo::class])
        ->callAction(TestAction::make('duplicar')->table($this->origen), ['nombre' => 'Masculina 4ª', 'nivel' => '4ª'])
        ->assertHasNoActionErrors();

    expect(Categoria::query()->where('nombre', 'Masculina 4ª')->sole()->nivel)->toBe('4ª');

    Livewire::test(CategoriasRelationManager::class, ['ownerRecord' => $this->torneo, 'pageClass' => EditTorneo::class])
        ->callAction(TestAction::make('duplicar')->table($this->origen), ['nombre' => 'Mixta', 'genero' => 'mixto', 'con_fases' => false])
        ->assertHasNoActionErrors();

    expect(Categoria::query()->where('nombre', 'Mixta')->sole()->fases()->count())->toBe(0);
});

test('un administrador del torneo también puede duplicar', function () {
    $administrador = User::factory()->rol(Rol::Administrador)->create();
    $this->torneo->administradores()->attach($administrador);
    $this->actingAs($administrador);

    Livewire::test(CategoriasRelationManager::class, ['ownerRecord' => $this->torneo, 'pageClass' => EditTorneo::class])
        ->assertActionVisible(TestAction::make('duplicar')->table($this->origen));
});
