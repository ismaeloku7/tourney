<?php

use App\Enums\Rol;
use App\Filament\Resources\Categorias\Pages\EditCategoria;
use App\Filament\Resources\Categorias\RelationManagers\InscripcionesRelationManager;
use App\Filament\Resources\Parejas\Pages\ListParejas;
use App\Models\Categoria;
use App\Models\Inscripcion;
use App\Models\Jugador;
use App\Models\Organizador;
use App\Models\Participante;
use App\Models\RegistroDeCambio;
use App\Models\Torneo;
use App\Models\User;
use Database\Seeders\PlanesSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

/** La organización pone el nivel de cada jugador de una pareja desde el panel. */
beforeEach(function () {
    $this->seed(PlanesSeeder::class);
    usarOrganizador(Organizador::factory()->create());
    Filament::setCurrentPanel('panel');

    $this->propietario = User::factory()->rol(Rol::Propietario)->create();
    $this->categoria = Categoria::factory()->create(['torneo_id' => Torneo::factory()->create()->id]);
    $this->ana = Jugador::factory()->create(['nombre' => 'Ana', 'nivel_declarado' => null]);
    $this->eva = Jugador::factory()->create(['nombre' => 'Eva', 'nivel_declarado' => '2']);
    $this->inscripcion = Inscripcion::factory()->create([
        'participante_id' => Participante::factory()->conJugadores($this->ana, $this->eva)->create(['categoria_id' => $this->categoria->id])->id,
    ]);
    $this->actingAs($this->propietario);
});

test('desde Parejas se ponen los niveles de los dos jugadores', function () {
    Livewire::test(ListParejas::class)
        ->callAction(TestAction::make('niveles')->table($this->inscripcion), [
            "nivel_{$this->ana->id}" => '3,5',
            "nivel_{$this->eva->id}" => '2,5',
        ])
        ->assertHasNoActionErrors()
        ->assertNotified('Niveles guardados');

    expect($this->ana->refresh()->nivel_declarado)->toBe('3,5')
        ->and($this->eva->refresh()->nivel_declarado)->toBe('2,5')
        ->and(RegistroDeCambio::query()->where('accion', 'cambiar_nivel')->pluck('user_id')->unique()->all())->toBe([$this->propietario->id]);
});

test('lo que no es un nivel no se guarda', function () {
    Livewire::test(ListParejas::class)
        ->callAction(TestAction::make('niveles')->table($this->inscripcion), ["nivel_{$this->ana->id}" => 'muy bueno', "nivel_{$this->eva->id}" => '2'])
        ->assertHasActionErrors(["nivel_{$this->ana->id}"]);

    Livewire::test(ListParejas::class)
        ->callAction(TestAction::make('niveles')->table($this->inscripcion), ["nivel_{$this->ana->id}" => '12', "nivel_{$this->eva->id}" => '2'])
        ->assertNotified('No se han podido guardar');

    expect($this->ana->refresh()->nivel_declarado)->toBeNull();
});

test('también desde las inscripciones de la categoría', function () {
    Livewire::test(InscripcionesRelationManager::class, ['ownerRecord' => $this->categoria, 'pageClass' => EditCategoria::class])
        ->callAction(TestAction::make('niveles')->table($this->inscripcion), ["nivel_{$this->ana->id}" => '4', "nivel_{$this->eva->id}" => ''])
        ->assertHasNoActionErrors();

    expect($this->ana->refresh()->nivel_declarado)->toBe('4')
        ->and($this->eva->refresh()->nivel_declarado)->toBeNull();
});
