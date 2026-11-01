<?php

use App\Livewire\Publico\AccesoJugador;
use App\Mail\EnlaceDeAcceso;
use App\Models\EnlaceMagico;
use App\Models\Jugador;
use App\Models\Organizador;
use Database\Seeders\PlanesSeeder;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PlanesSeeder::class);
    Mail::fake();
    $this->organizador = Organizador::factory()->create(['id' => 'norte']);
    usarOrganizador($this->organizador);
    $this->jugador = Jugador::factory()->create(['nombre' => 'Ana', 'email' => 'ana@example.com']);
});

function tokenDelCorreo(): string
{
    $enlace = null;
    Mail::assertSent(EnlaceDeAcceso::class, function (EnlaceDeAcceso $correo) use (&$enlace): bool {
        $enlace = $correo->enlace;

        return true;
    });

    preg_match('#/jugador/entrar/([A-Za-z0-9]{64})#', (string) $enlace, $m);

    return $m[1];
}

test('un jugador pide el enlace, entra con él una sola vez y vuelve a donde iba', function () {
    Livewire::withQueryParams(['destino' => '/torneos/open/inscripcion'])->test(AccesoJugador::class)
        ->set('email', 'ANA@example.com')
        ->call('enviar')
        ->assertSet('enviado', true)
        ->assertSee('te acabamos de mandar un enlace');

    Mail::assertSent(EnlaceDeAcceso::class, fn (EnlaceDeAcceso $c): bool => $c->hasTo('ana@example.com') && str_contains($c->enlace, 'destino=%2Ftorneos%2Fopen%2Finscripcion'));
    $token = tokenDelCorreo();

    expect(EnlaceMagico::query()->sole()->token)->toBe(hash('sha256', $token));

    $this->get("http://norte.saas.test/jugador/entrar/{$token}?destino=/torneos/open/inscripcion")
        ->assertRedirect('/torneos/open/inscripcion');
    $this->assertAuthenticatedAs($this->jugador, 'jugador');

    auth('jugador')->logout();
    $this->get("http://norte.saas.test/jugador/entrar/{$token}")->assertOk()->assertSee('Este enlace ya no vale');
    $this->assertGuest('jugador');
});

test('un enlace caducado no vale y un destino de fuera se ignora', function () {
    Livewire::test(AccesoJugador::class)->set('email', 'ana@example.com')->call('enviar');
    $token = tokenDelCorreo();

    $this->travel(16)->minutes();
    $this->get("http://norte.saas.test/jugador/entrar/{$token}")->assertSee('Este enlace ya no vale');
    $this->assertGuest('jugador');
    $this->travelBack();

    Mail::fake();
    Livewire::test(AccesoJugador::class)->set('email', 'ana@example.com')->call('enviar');
    $otro = tokenDelCorreo();
    $this->get("http://norte.saas.test/jugador/entrar/{$otro}?destino=//malo.example.com")->assertRedirect(route('jugador.portal'));
});

test('a un correo desconocido no se le manda nada y la respuesta es la misma', function () {
    Livewire::test(AccesoJugador::class)
        ->set('email', 'nadie@example.com')
        ->call('enviar')
        ->assertSet('enviado', true);

    Mail::assertNothingSent();
});

test('el portal sin sesión lleva al acceso', function () {
    $this->get('http://norte.saas.test/jugador')->assertRedirect(route('jugador.acceso', ['destino' => '/jugador']));
});
