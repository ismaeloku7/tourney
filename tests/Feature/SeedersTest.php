<?php

use App\Enums\Rol;
use App\Models\FormatoPartido;
use App\Models\Jugador;
use App\Models\Organizador;
use App\Models\Pista;
use App\Models\Plan;
use App\Models\User;
use App\Models\UsuarioPlataforma;

test('el seeder deja la plataforma y el organizador demo completos, y se puede repetir sin duplicar', function () {
    $this->seed();
    $this->seed();

    expect(Plan::query()->pluck('clave')->all())->toEqualCanonicalizing(['gratis', 'club', 'organizador'])
        ->and(UsuarioPlataforma::query()->count())->toBe(1)
        ->and(Organizador::query()->count())->toBe(1);

    $demo = Organizador::query()->findOrFail('demo');

    expect($demo->plan->clave)->toBe(Plan::CLUB);

    comoOrganizador($demo, function (): void {
        expect(User::query()->pluck('rol', 'email')->all())->toEqual([
            'propietario@example.com' => Rol::Propietario,
            'administrador@example.com' => Rol::Administrador,
            'arbitro@example.com' => Rol::Arbitro,
        ])
            ->and(Pista::query()->count())->toBe(4)
            ->and(FormatoPartido::query()->count())->toBe(4)
            ->and(FormatoPartido::query()->where('es_predeterminado', true)->count())->toBe(1)
            ->and(Jugador::query()->count())->toBe(48)
            ->and(Jugador::query()->where('email', 'jugador@example.com')->exists())->toBeTrue();
    });
});
