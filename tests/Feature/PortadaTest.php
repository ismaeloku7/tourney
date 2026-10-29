<?php

test('la portada responde en español', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Gestión de torneos de pádel');
});

test('la aplicación trabaja en hora de Madrid y en español', function () {
    expect(config('app.timezone'))->toBe('Europe/Madrid')
        ->and(config('app.locale'))->toBe('es')
        ->and(config('app.faker_locale'))->toBe('es_ES');
});
