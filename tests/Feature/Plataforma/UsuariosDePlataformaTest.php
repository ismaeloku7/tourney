<?php

use App\Models\UsuarioPlataforma;

test('el comando crea el primer usuario de la plataforma', function () {
    $this->artisan('plataforma:administrador')
        ->expectsQuestion('Nombre', 'Ana')
        ->expectsQuestion('Correo electrónico', 'ANA@example.com')
        ->expectsQuestion('Contraseña (al menos 12 caracteres)', 'una-clave-larga')
        ->assertSuccessful();

    expect(UsuarioPlataforma::query()->where('email', 'ana@example.com')->exists())->toBeTrue();
});

test('el comando no acepta una clave corta', function () {
    $this->artisan('plataforma:administrador')
        ->expectsQuestion('Nombre', 'Ana')
        ->expectsQuestion('Correo electrónico', 'ana@example.com')
        ->expectsQuestion('Contraseña (al menos 12 caracteres)', 'corta')
        ->assertFailed();

    expect(UsuarioPlataforma::query()->count())->toBe(0);
});
