<?php

use App\Filament\Pages\Auth\Login;
use App\Models\Organizador;
use App\Models\User;
use Database\Seeders\PlanesSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

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

test('mantiene la sesión iniciada 30 días si se deja marcado, y está marcado de entrada', function () {
    $recordar = fn (): ?SymfonyCookie => collect(app('cookie')->getQueuedCookies())->first(fn (SymfonyCookie $c): bool => str_starts_with($c->getName(), 'remember_web_'));

    Livewire::test(Login::class)
        ->assertFormFieldExists('remember')
        ->fillForm(['email' => 'ana@example.com', 'password' => 'clave-buena-12'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $cookie = $recordar();
    expect($cookie)->not->toBeNull()
        ->and($cookie->getExpiresTime())->toBeGreaterThan(now()->addDays(29)->getTimestamp())
        ->and($cookie->getExpiresTime())->toBeLessThan(now()->addDays(31)->getTimestamp());
});

test('sin marcarlo no deja la sesión recordada', function () {
    Livewire::test(Login::class)
        ->fillForm(['email' => 'ana@example.com', 'password' => 'clave-buena-12', 'remember' => false])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    expect(Auth::id())->toBe($this->usuario->id)
        ->and(collect(app('cookie')->getQueuedCookies())->contains(fn (SymfonyCookie $c): bool => str_starts_with($c->getName(), 'remember_web_')))->toBeFalse();
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
