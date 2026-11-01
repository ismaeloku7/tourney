<?php

use App\Enums\Rol;
use App\Models\Organizador;
use App\Models\Plan;
use App\Models\User;
use App\Models\UsuarioPlataforma;

test('el seeder deja planes, un usuario de plataforma y el organizador demo, y se puede repetir sin duplicar', function () {
    $this->seed();
    $this->seed();

    expect(Plan::query()->pluck('clave')->all())->toEqualCanonicalizing(['gratis', 'club', 'organizador'])
        ->and(UsuarioPlataforma::query()->count())->toBe(1)
        ->and(Organizador::query()->count())->toBe(1);

    $demo = Organizador::query()->findOrFail('demo');

    expect($demo->plan->clave)->toBe(Plan::CLUB)
        ->and(comoOrganizador($demo, fn () => User::query()->where('email', 'propietario@example.com')->sole())->rol)->toBe(Rol::Propietario);
});
