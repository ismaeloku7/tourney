<?php

use App\Enums\EstadoTorneo;
use App\Enums\Rol;
use App\Filament\Resources\Torneos\Pages\CreateTorneo;
use App\Filament\Resources\Torneos\Pages\EditTorneo;
use App\Filament\Resources\Torneos\Pages\ListTorneos;
use App\Filament\Resources\Torneos\RelationManagers\CategoriasRelationManager;
use App\Models\Categoria;
use App\Models\Organizador;
use App\Models\Sede;
use App\Models\Torneo;
use App\Models\User;
use Database\Seeders\PlanesSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PlanesSeeder::class);
    usarOrganizador(Organizador::factory()->create());
    Filament::setCurrentPanel('panel');

    $this->propietario = User::factory()->rol(Rol::Propietario)->create();
    $this->administrador = User::factory()->rol(Rol::Administrador)->create();
});

test('el propietario crea un torneo en borrador con su dirección pública', function () {
    $sede = Sede::factory()->create();
    $this->actingAs($this->propietario);

    Livewire::test(CreateTorneo::class)
        ->fillForm([
            'nombre' => 'Open de Primavera 2027',
            'slug' => 'open-de-primavera-2027',
            'sede_id' => $sede->id,
            'fecha_inicio' => '2027-04-10',
            'fecha_fin' => '2027-04-11',
            'inscripcion_desde' => '2027-03-01 09:00',
            'inscripcion_hasta' => '2027-04-05 23:00',
            'resultados_por_jugadores' => 'con_validacion',
            'minutos_para_validar' => 30,
            'descanso_minimo_minutos' => 30,
            'margen_entre_partidos_minutos' => 5,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $torneo = Torneo::query()->where('slug', 'open-de-primavera-2027')->sole();

    expect($torneo->estado)->toBe(EstadoTorneo::Borrador)
        ->and($torneo->creado_por)->toBe($this->propietario->id)
        ->and($torneo->sede_id)->toBe($sede->id)
        ->and($torneo->margen_entre_partidos_minutos)->toBe(5);
});

test('la dirección pública es obligatoria, sin espacios y única', function () {
    Torneo::factory()->create(['slug' => 'cogido']);
    $this->actingAs($this->propietario);

    Livewire::test(CreateTorneo::class)
        ->fillForm(['nombre' => 'X', 'slug' => 'cogido', 'fecha_inicio' => '2027-04-10', 'fecha_fin' => '2027-04-11'])
        ->call('create')
        ->assertHasFormErrors(['slug']);

    Livewire::test(CreateTorneo::class)
        ->fillForm(['nombre' => 'X', 'slug' => 'con espacio', 'fecha_inicio' => '2027-04-10', 'fecha_fin' => '2027-04-09'])
        ->call('create')
        ->assertHasFormErrors(['slug', 'fecha_fin']);
});

test('un administrador solo ve los torneos que tiene asignados y no crea torneos', function () {
    $suyo = Torneo::factory()->create();
    $ajeno = Torneo::factory()->create();
    $suyo->administradores()->attach($this->administrador);

    $this->actingAs($this->administrador);

    Livewire::test(ListTorneos::class)
        ->assertCanSeeTableRecords([$suyo])
        ->assertCanNotSeeTableRecords([$ajeno]);

    Livewire::test(CreateTorneo::class)->assertForbidden();
    // El torneo ajeno queda fuera de su consulta: para él no existe.
    Livewire::test(EditTorneo::class, ['record' => $ajeno->getRouteKey()])->assertNotFound();
    Livewire::test(EditTorneo::class, ['record' => $suyo->getRouteKey()])->assertSuccessful();

    $this->actingAs($this->propietario);
    Livewire::test(ListTorneos::class)->assertCanSeeTableRecords([$suyo, $ajeno]);
});

test('las categorías del torneo se ven en su ficha', function () {
    $torneo = Torneo::factory()->create();
    $categoria = Categoria::factory()->create(['torneo_id' => $torneo->id, 'nombre' => 'Mixta B']);
    $this->actingAs($this->propietario);

    Livewire::test(CategoriasRelationManager::class, ['ownerRecord' => $torneo, 'pageClass' => EditTorneo::class])
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$categoria])
        ->assertSee('Mixta B');
});
