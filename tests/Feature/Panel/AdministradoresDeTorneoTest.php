<?php

use App\Enums\Rol;
use App\Filament\Resources\Torneos\Pages\EditTorneo;
use App\Filament\Resources\Torneos\Pages\ListTorneos;
use App\Filament\Resources\Torneos\RelationManagers\AdministradoresRelationManager;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\Organizador;
use App\Models\Torneo;
use App\Models\User;
use Database\Seeders\PlanesSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

/** El propietario reparte los torneos entre los administradores. */
beforeEach(function () {
    $this->seed(PlanesSeeder::class);
    usarOrganizador(Organizador::factory()->create());
    Filament::setCurrentPanel('panel');

    $this->propietario = User::factory()->rol(Rol::Propietario)->create();
    $this->juan = User::factory()->rol(Rol::Administrador)->create(['nombre' => 'Juan Vicente', 'email' => 'juan@example.com']);
    $this->rally = Torneo::factory()->create(['nombre' => 'Rally 2026']);
    $this->open = Torneo::factory()->create(['nombre' => 'Open de Otoño']);
});

test('desde el torneo, el propietario añade un administrador, que entonces lo ve', function () {
    $this->actingAs($this->juan);
    Livewire::test(ListTorneos::class)->assertCanNotSeeTableRecords([$this->rally]);

    $this->actingAs($this->propietario);
    Livewire::test(AdministradoresRelationManager::class, ['ownerRecord' => $this->rally, 'pageClass' => EditTorneo::class])
        ->callAction(TestAction::make('attach')->table(), ['recordId' => $this->juan->id])
        ->assertHasNoActionErrors();

    expect($this->rally->administradores()->pluck('users.id')->all())->toBe([$this->juan->id]);

    $this->actingAs($this->juan);
    Livewire::test(ListTorneos::class)
        ->assertCanSeeTableRecords([$this->rally])
        ->assertCanNotSeeTableRecords([$this->open]);
});

test('un administrador no ve la pestaña de administradores', function () {
    $this->rally->administradores()->attach($this->juan);

    expect(AdministradoresRelationManager::canViewForRecord($this->rally, EditTorneo::class))->toBeFalse();
    $this->actingAs($this->juan);
    expect(AdministradoresRelationManager::canViewForRecord($this->rally, EditTorneo::class))->toBeFalse();
    $this->actingAs($this->propietario);
    expect(AdministradoresRelationManager::canViewForRecord($this->rally, EditTorneo::class))->toBeTrue();
});

test('al dar de alta a un administrador se le asignan sus torneos', function () {
    $this->actingAs($this->propietario);

    Livewire::test(CreateUser::class)
        ->fillForm([
            'nombre' => 'Marta',
            'email' => 'marta@example.com',
            'rol' => Rol::Administrador->value,
            'password' => 'una-clave-larga-2026',
            'activo' => true,
            'torneosQueAdministra' => [$this->open->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $marta = User::query()->where('email', 'marta@example.com')->sole();
    expect($marta->torneosQueAdministra()->pluck('torneos.id')->all())->toBe([$this->open->id]);
});
