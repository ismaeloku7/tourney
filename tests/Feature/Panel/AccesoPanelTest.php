<?php

use App\Enums\Rol;
use App\Models\Organizador;
use App\Models\User;
use Database\Seeders\PlanesSeeder;

beforeEach(function () {
    $this->seed(PlanesSeeder::class);
    $this->organizador = Organizador::factory()->create(['id' => 'norte', 'nombre' => 'Pádel Norte', 'dominio' => 'padel-norte.test']);
});

test('la raíz es pública y no lleva al panel', function () {
    $this->get('http://padel-norte.test/')->assertOk()->assertDontSee('/panel/login');
});

test('sin sesión, el panel lleva al login', function () {
    $this->get('http://padel-norte.test/panel')->assertRedirect('http://padel-norte.test/panel/login');
});

test('el login lleva el nombre del organizador del host, por subdominio y por dominio propio', function () {
    $this->get('http://padel-norte.test/panel/login')->assertOk()->assertSee('Pádel Norte');
    $this->get('http://norte.saas.test/panel/login')->assertOk()->assertSee('Pádel Norte');
});

test('el panel de un host desconocido y el del dominio del SaaS responden 404', function () {
    $this->get('http://desconocido.test/panel/login')->assertNotFound();
    $this->get('http://saas.test/panel/login')->assertNotFound();
});

test('el propietario y un administrador entran; un árbitro, no', function () {
    $propietario = comoOrganizador($this->organizador, fn () => User::factory()->rol(Rol::Propietario)->create());
    $administrador = comoOrganizador($this->organizador, fn () => User::factory()->rol(Rol::Administrador)->create());
    $arbitro = comoOrganizador($this->organizador, fn () => User::factory()->rol(Rol::Arbitro)->create());

    $this->actingAs($propietario)->get('http://padel-norte.test/panel')->assertOk()->assertSee('Panel');
    $this->actingAs($administrador)->get('http://padel-norte.test/panel')->assertOk();
    $this->actingAs($arbitro)->get('http://padel-norte.test/panel')->assertForbidden();
});

test('un usuario desactivado con la sesión abierta pierde el acceso', function () {
    $usuario = comoOrganizador($this->organizador, fn () => User::factory()->inactivo()->create());

    $this->actingAs($usuario)->get('http://padel-norte.test/panel')->assertForbidden();
});

test('el panel no manda el nombre del equipo a servicios de fuera', function () {
    $usuario = comoOrganizador($this->organizador, fn () => User::factory()->rol(Rol::Propietario)->create(['nombre' => 'Ana', 'apellidos' => 'Pérez']));

    $this->actingAs($usuario)->get('http://padel-norte.test/panel')->assertOk()->assertDontSee('ui-avatars.com');
});

test('el menú del propietario tiene instalaciones y equipo', function () {
    $propietario = comoOrganizador($this->organizador, fn () => User::factory()->rol(Rol::Propietario)->create());

    $this->actingAs($propietario)->get('http://padel-norte.test/panel')->assertOk()
        ->assertSee('http://padel-norte.test/panel/torneos', false)
        ->assertSee('http://padel-norte.test/panel/pistas', false)
        ->assertSee('http://padel-norte.test/panel/equipo', false);
});

// En una prueba aparte: el menú se calcula una vez por proceso y el del
// propietario se quedaría puesto.
test('el menú del administrador no tiene instalaciones ni equipo', function () {
    $administrador = comoOrganizador($this->organizador, fn () => User::factory()->rol(Rol::Administrador)->create());

    $this->actingAs($administrador)->get('http://padel-norte.test/panel')->assertOk()
        ->assertSee('http://padel-norte.test/panel/torneos', false)
        ->assertDontSee('http://padel-norte.test/panel/pistas', false)
        ->assertDontSee('http://padel-norte.test/panel/equipo', false);

    $this->actingAs($administrador)->get('http://padel-norte.test/panel/equipo')->assertForbidden();
});
