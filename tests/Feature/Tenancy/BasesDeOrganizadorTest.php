<?php

use App\Enums\Rol;
use App\Models\Organizador;
use App\Models\User;
use App\Tenancy\OrganizadorActual;
use App\Tenancy\SinOrganizadorActual;
use Database\Seeders\PlanesSeeder;

beforeEach(function () {
    $this->seed(PlanesSeeder::class);
});

test('crear un organizador crea su base, migrada, y su subdominio', function () {
    $organizador = Organizador::factory()->create(['id' => 'norte']);

    expect($organizador->nombreDeBase())->toBe('prueba_organizador_norte.sqlite')
        ->and(file_exists(database_path('prueba_organizador_norte.sqlite')))->toBeTrue()
        ->and($organizador->domains->pluck('domain')->all())->toBe(['norte'])
        ->and(comoOrganizador($organizador, fn () => User::query()->count()))->toBe(0);
});

test('sin organizador activo, un modelo de su base no busca en la central', function () {
    expect(fn () => User::query()->count())->toThrow(SinOrganizadorActual::class);
});

test('el equipo de un organizador no se ve desde otro', function () {
    $a = Organizador::factory()->create(['id' => 'aaa']);
    $b = Organizador::factory()->create(['id' => 'bbb']);

    comoOrganizador($a, fn () => User::factory()->count(2)->create());
    comoOrganizador($b, fn () => User::factory()->rol(Rol::Propietario)->create(['email' => 'solo-en-b@example.com']));

    expect(comoOrganizador($a, fn () => User::query()->count()))->toBe(2)
        ->and(comoOrganizador($b, fn () => User::query()->count()))->toBe(1)
        ->and(comoOrganizador($a, fn () => User::query()->where('email', 'solo-en-b@example.com')->exists()))->toBeFalse();
});

test('ejecutarComo deja después el organizador que hubiera antes, también si falla', function () {
    $a = Organizador::factory()->create(['id' => 'aaa']);
    $b = Organizador::factory()->create(['id' => 'bbb']);
    $actual = app(OrganizadorActual::class);

    expect($actual->hay())->toBeFalse();

    comoOrganizador($a, function () use ($actual, $b): void {
        expect($actual->slug())->toBe('aaa');

        try {
            comoOrganizador($b, fn () => throw new RuntimeException('algo'));
        } catch (RuntimeException) {
        }

        expect($actual->slug())->toBe('aaa');
    });

    expect($actual->hay())->toBeFalse()
        ->and(fn () => $actual->organizador())->toThrow(SinOrganizadorActual::class);
});

test('borrar un organizador borra su base', function () {
    $organizador = Organizador::factory()->create(['id' => 'efimero']);
    $ruta = database_path('prueba_organizador_efimero.sqlite');

    expect(file_exists($ruta))->toBeTrue();

    $organizador->delete();

    expect(file_exists($ruta))->toBeFalse()
        ->and(Organizador::query()->find('efimero'))->toBeNull();
});
