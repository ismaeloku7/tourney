<?php

use App\Enums\Ajuste;
use App\Enums\Rol;
use App\Models\Organizador;
use App\Models\User;
use App\Models\ValorDeAjuste;
use Database\Seeders\PlanesSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(PlanesSeeder::class);
    $this->organizador = Organizador::factory()->create(['id' => 'norte']);
    comoOrganizador($this->organizador, function (): void {
        User::factory()->rol(Rol::Propietario)->inactivo()->create(['email' => 'duena@padelnorte.es', 'password' => 'la-de-antes-123']);
        ValorDeAjuste::fijar([Ajuste::CorreoPropio->value => true, Ajuste::CorreoServidor->value => 'smtp.padelnorte.es']);
    });
});

test('pone una contraseña nueva, activa la cuenta y, si se pide, apaga el correo propio', function () {
    $this->artisan('equipo:recuperar-acceso', ['organizador' => 'norte', 'email' => 'Duena@PadelNorte.es ', '--sin-correo-propio' => true])
        ->expectsOutputToContain('Contraseña nueva de duena@padelnorte.es')
        ->assertSuccessful();

    comoOrganizador($this->organizador, function (): void {
        $usuario = User::query()->where('email', 'duena@padelnorte.es')->sole();

        expect($usuario->activo)->toBeTrue()
            ->and(Hash::check('la-de-antes-123', $usuario->password))->toBeFalse()
            ->and(ValorDeAjuste::obtener(Ajuste::CorreoPropio))->toBeFalse()
            ->and(ValorDeAjuste::obtener(Ajuste::CorreoServidor))->toBe('smtp.padelnorte.es');
    });
});

test('sin --sin-correo-propio el correo se queda como estaba, y con datos que no existen no toca nada', function () {
    $this->artisan('equipo:recuperar-acceso', ['organizador' => 'norte', 'email' => 'duena@padelnorte.es'])->assertSuccessful();
    comoOrganizador($this->organizador, fn () => expect(ValorDeAjuste::obtener(Ajuste::CorreoPropio))->toBeTrue());

    $this->artisan('equipo:recuperar-acceso', ['organizador' => 'sur', 'email' => 'duena@padelnorte.es'])->assertFailed();
    $this->artisan('equipo:recuperar-acceso', ['organizador' => 'norte', 'email' => 'nadie@padelnorte.es'])->assertFailed();
});

test('con --clave pone la que se le dé, para no depender de leer la salida', function () {
    $this->artisan('equipo:recuperar-acceso', ['organizador' => 'norte', 'email' => 'duena@padelnorte.es', '--clave' => 'corta'])->assertFailed();

    $this->artisan('equipo:recuperar-acceso', ['organizador' => 'norte', 'email' => 'duena@padelnorte.es', '--clave' => 'una-clave-nueva-2026'])
        ->expectsOutputToContain('Contraseña guardada y cuenta activa.')
        ->expectsOutputToContain('Listo: ya puede entrar como duena@padelnorte.es')
        ->doesntExpectOutputToContain('una-clave-nueva-2026')
        ->assertSuccessful();

    comoOrganizador($this->organizador, function (): void {
        $usuario = User::query()->where('email', 'duena@padelnorte.es')->sole();

        expect(Hash::check('una-clave-nueva-2026', $usuario->password))->toBeTrue()
            ->and($usuario->activo)->toBeTrue();
    });
});
