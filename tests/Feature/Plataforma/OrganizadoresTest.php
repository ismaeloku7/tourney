<?php

use App\Actions\Plataforma\CambiarEstadoDelOrganizador;
use App\Enums\EstadoOrganizador;
use App\Enums\Rol;
use App\Models\AuditoriaGlobal;
use App\Models\Organizador;
use App\Models\Plan;
use App\Models\SuscripcionPlan;
use App\Models\User;
use Database\Seeders\PlanesSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(PlanesSeeder::class);
});

test('el comando da de alta un organizador con base, subdominio, dominio propio, plan y propietario', function () {
    $this->artisan('organizadores:crear', [
        'slug' => 'Ceas',
        'nombre' => 'Club CEAS',
        'email' => 'Padel@ClubCeas.es',
        '--responsable' => 'Marta Gil',
        '--dominio' => 'WWW.Tourney.ClubCeas.es',
        '--plan' => 'club',
        '--clave' => 'una-clave-muy-larga',
    ])->assertSuccessful();

    $organizador = Organizador::query()->findOrFail('ceas');

    expect($organizador->estado)->toBe(EstadoOrganizador::Activo)
        ->and($organizador->plan->clave)->toBe(Plan::CLUB)
        ->and($organizador->responsable_email)->toBe('padel@clubceas.es')
        ->and($organizador->domains->pluck('domain')->all())->toEqualCanonicalizing(['ceas', 'tourney.clubceas.es'])
        ->and($organizador->dominio)->toBe('tourney.clubceas.es');

    $propietario = comoOrganizador($organizador, fn () => User::query()->sole());

    expect($propietario)->nombre->toBe('Marta Gil')->email->toBe('padel@clubceas.es')->rol->toBe(Rol::Propietario)
        ->and(Hash::check('una-clave-muy-larga', $propietario->password))->toBeTrue();

    expect(SuscripcionPlan::query()->where('organizador_id', 'ceas')->whereNull('hasta')->count())->toBe(1)
        ->and(AuditoriaGlobal::query()->where('accion', 'crear')->where('organizador_id', 'ceas')->exists())->toBeTrue();

    // Y ya responde en sus dos direcciones.
    $this->get('http://ceas.saas.test/')->assertOk()->assertSee('Club CEAS');
    $this->get('http://tourney.clubceas.es/')->assertOk()->assertSee('Club CEAS');
});

test('sin contraseña, el comando genera una y la enseña una sola vez', function () {
    $this->artisan('organizadores:crear', ['slug' => 'demo', 'nombre' => 'Club Demo', 'email' => 'p@example.com'])
        ->expectsOutputToContain('Contraseña del propietario')
        ->assertSuccessful();

    $organizador = Organizador::query()->findOrFail('demo');

    expect($organizador->responsable_password)->toBeNull()
        ->and($organizador->plan->clave)->toBe(Plan::GRATIS)
        ->and(comoOrganizador($organizador, fn () => User::query()->count()))->toBe(1);
});

test('el slug tiene que valer como subdominio y no estar cogido', function (string $slug) {
    Organizador::factory()->create(['id' => 'cogido', 'dominio' => 'propio.test']);

    $this->artisan('organizadores:crear', ['slug' => $slug, 'nombre' => 'X', 'email' => 'x@example.com'])->assertFailed();

    expect(Organizador::query()->count())->toBe(1);
})->with([
    'repetido' => 'cogido',
    'reservado' => 'plataforma',
    'con espacio' => 'club norte',
    'con tilde' => 'pádel',
    'corto' => 'ab',
    'acaba en guión' => 'norte-',
]);

test('el dominio propio no puede ser el de la plataforma, un subdominio suyo ni el de otro organizador', function (string $dominio) {
    Organizador::factory()->create(['id' => 'cogido', 'dominio' => 'propio.test']);

    $this->artisan('organizadores:crear', ['slug' => 'nuevo', 'nombre' => 'X', 'email' => 'x@example.com', '--dominio' => $dominio])->assertFailed();

    expect(Organizador::query()->find('nuevo'))->toBeNull();
})->with([
    'el del SaaS' => 'saas.test',
    'subdominio del SaaS' => 'nuevo.saas.test',
    'de otro' => 'propio.test',
    'sin forma de dominio' => 'no es un dominio',
]);

test('un plan que no existe para el alta antes de crear nada', function () {
    $this->artisan('organizadores:crear', ['slug' => 'nuevo', 'nombre' => 'X', 'email' => 'x@example.com', '--plan' => 'premium'])->assertFailed();

    expect(Organizador::query()->count())->toBe(0);
});

test('destruir exige confirmación y borra la fila y la base', function () {
    $organizador = Organizador::factory()->create(['id' => 'efimero']);
    $ruta = database_path('prueba_organizador_efimero.sqlite');

    $this->artisan('organizadores:destruir', ['slug' => 'efimero'])->assertFailed();

    expect(Organizador::query()->find('efimero'))->not->toBeNull();

    $this->artisan('organizadores:destruir', ['slug' => 'efimero', '--confirmar' => true])->assertSuccessful();

    expect(Organizador::query()->find('efimero'))->toBeNull()
        ->and(file_exists($ruta))->toBeFalse()
        ->and(AuditoriaGlobal::query()->where('accion', 'destruir')->exists())->toBeTrue();

    $this->artisan('organizadores:destruir', ['slug' => 'efimero', '--confirmar' => true])->assertFailed();
});

test('suspender deja de servir los hosts y reactivar los devuelve', function () {
    $organizador = Organizador::factory()->create(['id' => 'norte', 'nombre' => 'Pádel Norte']);
    $accion = app(CambiarEstadoDelOrganizador::class);

    $accion->handle($organizador, EstadoOrganizador::Suspendido, motivo: 'impago');
    $this->get('http://norte.saas.test/')->assertNotFound();

    expect($organizador->fresh())->suspendido_en->not->toBeNull()->suspension_motivo->toBe('impago');

    $accion->handle($organizador, EstadoOrganizador::Activo);
    $this->get('http://norte.saas.test/')->assertOk();

    expect($organizador->fresh())->suspendido_en->toBeNull()
        ->and(AuditoriaGlobal::query()->where('organizador_id', 'norte')->pluck('accion')->all())->toBe(['suspendido', 'activo']);
});
