<?php

use App\Enums\Rol;
use App\Filament\Resources\Jugadores\Pages\EditJugador;
use App\Models\Jugador;
use App\Models\Organizador;
use App\Models\User;
use Database\Seeders\PlanesSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

/** La ficha de un jugador en el panel. */
beforeEach(function () {
    $this->seed(PlanesSeeder::class);
    usarOrganizador(Organizador::factory()->create());
    Filament::setCurrentPanel('panel');

    $this->ana = Jugador::factory()->create(['nombre' => 'Ana', 'apellidos' => 'García', 'email' => 'ana@example.com', 'telefono' => '600111222']);
});

test('al editar un jugador salen su correo y su teléfono, y se pueden cambiar', function () {
    $this->actingAs(User::factory()->rol(Rol::Propietario)->create());

    Livewire::test(EditJugador::class, ['record' => $this->ana->getRouteKey()])
        ->assertOk()
        ->assertFormSet(['nombre' => 'Ana', 'email' => 'ana@example.com', 'telefono' => '600111222'])
        ->fillForm(['email' => 'Ana.Garcia@Example.com', 'telefono' => '600333444'])
        ->call('save')
        ->assertHasNoFormErrors();

    $ana = $this->ana->refresh();
    expect($ana->getAttribute('email'))->toBe('ana.garcia@example.com')
        ->and($ana->getAttribute('telefono'))->toBe('600333444');
});

test('guardar sin tocar el correo ni el teléfono no los borra', function () {
    $this->actingAs(User::factory()->rol(Rol::Administrador)->create());

    Livewire::test(EditJugador::class, ['record' => $this->ana->getRouteKey()])
        ->fillForm(['nivel_declarado' => '3,5'])
        ->call('save')
        ->assertHasNoFormErrors();

    $ana = $this->ana->refresh();
    expect($ana->nivel_declarado)->toBe('3,5')
        ->and($ana->getAttribute('email'))->toBe('ana@example.com')
        ->and($ana->getAttribute('telefono'))->toBe('600111222');
});

test('un árbitro no edita jugadores', function () {
    $this->actingAs(User::factory()->rol(Rol::Arbitro)->create());

    // Un árbitro solo ve los jugadores de los torneos que arbitra: los demás, ni existen para él.
    Livewire::test(EditJugador::class, ['record' => $this->ana->getRouteKey()])->assertNotFound();
});
