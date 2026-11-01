<?php

use App\Filament\Pages\Auth\Login;
use App\Models\Organizador;
use App\Models\User;
use Database\Seeders\PlanesSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PlanesSeeder::class);
    usarOrganizador(Organizador::factory()->create());
    Filament::setCurrentPanel('panel');

    $this->usuario = User::factory()->create(['email' => 'ana@example.com', 'password' => 'clave-buena-12']);
});

test('entra con la clave buena y se anota el último acceso', function () {
    Livewire::test(Login::class)
        ->fillForm(['email' => 'ANA@example.com', 'password' => 'clave-buena-12'])
        ->call('authenticate')
        ->assertHasNoFormErrors()
        ->assertRedirect();

    expect(Auth::id())->toBe($this->usuario->id)
        ->and($this->usuario->fresh()->ultimo_acceso)->not->toBeNull();
});

test('con la clave mala da el error en el correo y no entra', function () {
    Livewire::test(Login::class)
        ->fillForm(['email' => 'ana@example.com', 'password' => 'otra-clave'])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    expect(Auth::check())->toBeFalse();
});

test('un usuario inactivo no entra aunque la clave sea buena', function () {
    $this->usuario->update(['activo' => false]);

    Livewire::test(Login::class)
        ->fillForm(['email' => 'ana@example.com', 'password' => 'clave-buena-12'])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    expect(Auth::check())->toBeFalse();
});

test('no ofrece «recordarme»', function () {
    Livewire::test(Login::class)->assertFormFieldDoesNotExist('remember');
});

test('tras cinco fallos bloquea también la clave buena', function () {
    $login = Livewire::test(Login::class);

    foreach (range(1, 5) as $intento) {
        $login->fillForm(['email' => 'ana@example.com', 'password' => 'otra-clave'])->call('authenticate');
    }

    $login->fillForm(['email' => 'ana@example.com', 'password' => 'clave-buena-12'])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    expect(Auth::check())->toBeFalse();
});
