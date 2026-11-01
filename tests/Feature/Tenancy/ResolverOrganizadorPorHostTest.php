<?php

use App\Http\Middleware\ResolverOrganizadorPorHost;
use App\Models\Organizador;
use Database\Seeders\PlanesSeeder;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->seed(PlanesSeeder::class);
});

test('el dominio del SaaS no es de ningún organizador', function () {
    $this->get('http://saas.test/')->assertOk()->assertSee('Gestión de torneos de pádel');

    expect(tenancy()->initialized)->toBeFalse();
});

test('un host desconocido responde 404', function () {
    $this->get('http://nadie.saas.test/')->assertNotFound();
    $this->get('http://otracosa.test/')->assertNotFound();
});

test('el subdominio del SaaS abre la portada del organizador', function () {
    Organizador::factory()->create(['id' => 'norte', 'nombre' => 'Pádel Norte']);

    $this->get('http://norte.saas.test/')->assertOk()->assertSee('Pádel Norte');
});

test('el dominio propio abre la portada, con o sin www y en cualquier caja', function () {
    Organizador::factory()->create(['id' => 'ceas', 'nombre' => 'Club CEAS', 'dominio' => 'tourney.clubceas.es']);

    $this->get('http://tourney.clubceas.es/')->assertOk()->assertSee('Club CEAS');
    $this->get('http://www.tourney.clubceas.es/')->assertOk()->assertSee('Club CEAS');
    $this->get('http://Tourney.ClubCeas.es/')->assertOk()->assertSee('Club CEAS');
});

test('un organizador suspendido o pendiente no sirve sus hosts', function () {
    Organizador::factory()->suspendido()->create(['id' => 'parado']);
    Organizador::factory()->pendiente()->create(['id' => 'nuevo']);

    $this->get('http://parado.saas.test/')->assertNotFound();
    $this->get('http://nuevo.saas.test/')->assertNotFound();
});

test('en local, un host de desarrollo sirve al organizador que diga la configuración', function () {
    config(['organizadores.hosts_desarrollo' => ['localhost' => 'demo']]);
    Organizador::factory()->create(['id' => 'demo', 'nombre' => 'Club Demo']);

    $this->get('http://localhost/')->assertOk()->assertSee('Club Demo');
});

test('al terminar la petición se deja el organizador que hubiera antes', function () {
    Organizador::factory()->create(['id' => 'norte']);

    $this->get('http://norte.saas.test/')->assertOk();

    expect(tenancy()->initialized)->toBeFalse();
});

test('el host por el que se entra queda apuntado al resolver el organizador', function () {
    Organizador::factory()->create(['id' => 'norte']);
    $visto = null;

    app(ResolverOrganizadorPorHost::class)->handle(
        Request::create('http://norte.saas.test/torneos'),
        function ($request) use (&$visto) {
            $visto = $request->attributes->get(ResolverOrganizadorPorHost::HOST);

            return response('ok');
        },
    );

    expect($visto)->toBe('http://norte.saas.test');
});

test('normalizar un host quita mayúsculas, puerto, punto final y www', function (string $host, string $esperado) {
    expect(Organizador::normalizarDominio($host))->toBe($esperado);
})->with([
    ['WWW.Tourney.ClubCeas.es:443', 'tourney.clubceas.es'],
    ['demo.saas.test.', 'demo.saas.test'],
    ['  localhost:8000 ', 'localhost'],
]);

test('fuera de una petición, la dirección es el dominio propio o el subdominio del SaaS', function () {
    $propio = Organizador::factory()->create(['id' => 'conpropio', 'dominio' => 'torneos-propio.test']);
    $sin = Organizador::factory()->create(['id' => 'sinpropio']);

    expect($propio->url('/torneos/x'))->toEndWith('://torneos-propio.test/torneos/x')
        ->and($sin->url('/torneos/x'))->toContain('://sinpropio.saas.test/torneos/x');
});

test('en una petición del organizador, los enlaces llevan a la dirección por la que se ha entrado', function () {
    $organizador = Organizador::factory()->create(['id' => 'ceas', 'dominio' => 'tourney.clubceas.es']);
    $otro = Organizador::factory()->create(['id' => 'otro', 'dominio' => 'otro.test']);

    usarOrganizador($organizador);
    request()->attributes->set(ResolverOrganizadorPorHost::HOST, 'http://localhost:8000');

    expect($organizador->url('/torneos/abc'))->toBe('http://localhost:8000/torneos/abc')
        ->and($otro->url('/torneos/abc'))->toEndWith('://otro.test/torneos/abc');

    request()->attributes->remove(ResolverOrganizadorPorHost::HOST);
});
