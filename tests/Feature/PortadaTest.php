<?php

test('la portada de la plataforma responde en el dominio del SaaS', function () {
    $this->get('http://saas.test/')
        ->assertOk()
        ->assertSee('Gestión de torneos de pádel');
});

test('la aplicación trabaja en hora de Madrid y en español', function () {
    expect(config('app.timezone'))->toBe('Europe/Madrid')
        ->and(config('app.locale'))->toBe('es')
        ->and(config('app.faker_locale'))->toBe('es_ES');
});
