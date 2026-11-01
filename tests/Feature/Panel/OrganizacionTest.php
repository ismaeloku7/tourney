<?php

use App\Enums\Rol;
use App\Filament\Resources\FormatosPartido\Pages\CreateFormatoPartido;
use App\Filament\Resources\Pistas\Pages\CreatePista;
use App\Filament\Resources\Sedes\Pages\CreateSede;
use App\Filament\Resources\Sedes\Pages\ListSedes;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\FormatoPartido;
use App\Models\Organizador;
use App\Models\Pista;
use App\Models\Sede;
use App\Models\User;
use Database\Seeders\PlanesSeeder;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PlanesSeeder::class);
    usarOrganizador(Organizador::factory()->create());
    Filament::setCurrentPanel('panel');

    $this->propietario = User::factory()->rol(Rol::Propietario)->create();
    $this->administrador = User::factory()->rol(Rol::Administrador)->create();
});

test('el propietario crea una sede y una pista en ella', function () {
    $this->actingAs($this->propietario);

    Livewire::test(CreateSede::class)
        ->fillForm(['nombre' => 'Club Norte', 'direccion' => 'Calle Mayor, 1', 'orden' => 1])
        ->call('create')
        ->assertHasNoFormErrors();

    $sede = Sede::query()->where('nombre', 'Club Norte')->sole();

    Livewire::test(CreatePista::class)
        ->fillForm(['nombre' => 'Central', 'sede_id' => $sede->id, 'orden' => 1, 'activa' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Pista::query()->where('nombre', 'Central')->sole()->sede_id)->toBe($sede->id);
});

test('un administrador de torneo no entra en instalaciones ni en equipo', function () {
    $this->actingAs($this->administrador);

    Livewire::test(ListSedes::class)->assertForbidden();
    Livewire::test(ListUsers::class)->assertForbidden();
});

test('un formato nuevo marcado como predeterminado desbanca al anterior', function () {
    $anterior = FormatoPartido::factory()->predeterminado()->create();
    $this->actingAs($this->propietario);

    Livewire::test(CreateFormatoPartido::class)
        ->fillForm(['nombre' => 'Un set a 9', 'sets' => 1, 'juegos_por_set' => 9, 'diferencia_para_ganar_set' => 2, 'tie_break_en' => 8, 'tie_break_puntos' => 7, 'duracion_estimada' => 60, 'es_predeterminado' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(FormatoPartido::query()->where('es_predeterminado', true)->sole()->nombre)->toBe('Un set a 9')
        ->and($anterior->fresh()->es_predeterminado)->toBeFalse();
});

test('el propietario da de alta a un administrador y nadie se borra a sí mismo', function () {
    $this->actingAs($this->propietario);

    Livewire::test(CreateUser::class)
        ->fillForm(['nombre' => 'Luis', 'apellidos' => 'Gil', 'email' => 'Luis@Example.com', 'rol' => Rol::Administrador->value, 'password' => 'una-clave-larga', 'activo' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    $luis = User::query()->where('email', 'luis@example.com')->sole();

    expect($luis->rol)->toBe(Rol::Administrador)
        ->and(Hash::check('una-clave-larga', $luis->password))->toBeTrue();

    Livewire::test(CreateUser::class)
        ->fillForm(['nombre' => 'Ana', 'email' => 'ana@example.com', 'rol' => Rol::Arbitro->value, 'password' => 'corta'])
        ->call('create')
        ->assertHasFormErrors(['password']);

    Livewire::test(EditUser::class, ['record' => $this->propietario->getRouteKey()])->assertActionHidden(DeleteAction::class);
    Livewire::test(EditUser::class, ['record' => $luis->getRouteKey()])->assertActionVisible(DeleteAction::class);
});
