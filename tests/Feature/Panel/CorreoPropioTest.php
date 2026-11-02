<?php

use App\Enums\Ajuste;
use App\Enums\Rol;
use App\Filament\Pages\Ajustes;
use App\Mail\CorreoDePrueba;
use App\Models\Organizador;
use App\Models\RegistroDeCambio;
use App\Models\User;
use App\Models\ValorDeAjuste;
use App\Tenancy\CorreoDelOrganizador;
use Database\Seeders\PlanesSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

/** El organizador manda los correos por su propio servidor SMTP. */
beforeEach(function () {
    $this->seed(PlanesSeeder::class);
    $this->organizador = Organizador::factory()->create(['id' => 'norte', 'nombre' => 'Pádel Norte']);
    usarOrganizador($this->organizador);
    Filament::setCurrentPanel('panel');
    $this->propietario = User::factory()->rol(Rol::Propietario)->create(['email' => 'duena@padelnorte.es']);
    $this->actingAs($this->propietario);
});

function servidorDelClub(): array
{
    return [
        Ajuste::NombrePublico->value => 'Pádel Norte',
        Ajuste::CorreoPropio->value => true,
        Ajuste::CorreoServidor->value => 'smtp.padelnorte.es',
        Ajuste::CorreoCifrado->value => 'ssl',
        Ajuste::CorreoPuerto->value => '465',
        Ajuste::CorreoUsuario->value => 'torneos@padelnorte.es',
        Ajuste::CorreoClave->value => 'clave-secreta',
        Ajuste::CorreoRemitente->value => 'torneos@padelnorte.es',
        Ajuste::CorreoRemitenteNombre->value => 'Torneos Pádel Norte',
    ];
}

test('la contraseña se guarda cifrada, no vuelve al formulario ni al registro, y en blanco se queda', function () {
    Livewire::test(Ajustes::class)
        ->fillForm(servidorDelClub())
        ->call('guardar')
        ->assertHasNoFormErrors()
        ->assertNotified('Ajustes guardados')
        ->assertSet('data.'.Ajuste::CorreoClave->value, null);

    $guardada = ValorDeAjuste::obtener(Ajuste::CorreoClave);
    expect($guardada)->toBeString()->not->toBe('clave-secreta')
        ->and(Crypt::decryptString((string) $guardada))->toBe('clave-secreta')
        ->and(ValorDeAjuste::obtener(Ajuste::CorreoPuerto))->toBe(465)
        ->and(json_encode(RegistroDeCambio::query()->where('accion', 'ajustes')->sole()->getAttributes()))->not->toContain('clave-secreta')
        ->and(RegistroDeCambio::query()->where('accion', 'ajustes')->sole()->despues[Ajuste::CorreoClave->value])->toBe('cambiada');

    // Al volver a la pantalla no está, y guardar sin tocarla la conserva.
    Livewire::test(Ajustes::class)
        ->assertSet('data.'.Ajuste::CorreoClave->value, null)
        ->assertDontSee('clave-secreta')
        ->fillForm([Ajuste::CorreoServidor->value => 'mail.padelnorte.es'])
        ->call('guardar')
        ->assertHasNoFormErrors();

    expect(Crypt::decryptString((string) ValorDeAjuste::obtener(Ajuste::CorreoClave)))->toBe('clave-secreta')
        ->and(ValorDeAjuste::obtener(Ajuste::CorreoServidor))->toBe('mail.padelnorte.es');

    // Apagarlo no borra los datos del servidor.
    Livewire::test(Ajustes::class)->fillForm([Ajuste::CorreoPropio->value => false])->call('guardar')->assertHasNoFormErrors();
    expect(ValorDeAjuste::obtener(Ajuste::CorreoPropio))->toBeFalse()
        ->and(ValorDeAjuste::obtener(Ajuste::CorreoServidor))->toBe('mail.padelnorte.es');
});

test('con el servidor propio encendido, faltan el servidor y el remitente', function () {
    Livewire::test(Ajustes::class)
        ->fillForm([Ajuste::NombrePublico->value => 'Pádel Norte', Ajuste::CorreoPropio->value => true, Ajuste::CorreoServidor->value => '', Ajuste::CorreoRemitente->value => ''])
        ->call('guardar')
        ->assertHasFormErrors([Ajuste::CorreoServidor->value => 'required', Ajuste::CorreoRemitente->value => 'required']);
});

test('dentro del organizador el correo sale por su servidor, y al salir vuelve el de la plataforma', function () {
    $dePlataforma = config('mail.default');
    $remitenteDePlataforma = config('mail.from.address');

    // Sin servidor propio, el de la plataforma.
    tenancy()->end();
    tenancy()->initialize($this->organizador);
    expect(config('mail.default'))->toBe($dePlataforma);

    Livewire::test(Ajustes::class)->fillForm(servidorDelClub())->call('guardar')->assertHasNoFormErrors();

    tenancy()->end();
    tenancy()->initialize($this->organizador);

    expect(config('mail.default'))->toBe(CorreoDelOrganizador::MAILER)
        ->and(config('mail.mailers.organizador'))->toMatchArray([
            'transport' => 'smtp',
            'scheme' => 'smtps',
            'host' => 'smtp.padelnorte.es',
            'port' => 465,
            'username' => 'torneos@padelnorte.es',
            'password' => 'clave-secreta',
        ])
        ->and(config('mail.from'))->toBe(['address' => 'torneos@padelnorte.es', 'name' => 'Torneos Pádel Norte']);

    tenancy()->end();

    expect(config('mail.default'))->toBe($dePlataforma)
        ->and(config('mail.from.address'))->toBe($remitenteDePlataforma)
        ->and(config('mail.mailers.organizador'))->toBeNull();
});

test('«Guardar y enviarme un correo de prueba» lo manda al propietario desde el remitente del club', function () {
    Mail::fake();

    Livewire::test(Ajustes::class)
        ->fillForm(servidorDelClub())
        ->call('probarCorreo')
        ->assertHasNoFormErrors()
        ->assertNotified('Correo de prueba enviado');

    Mail::assertSent(CorreoDePrueba::class, fn (CorreoDePrueba $correo): bool => $correo->hasTo('duena@padelnorte.es')
        && $correo->hasFrom('torneos@padelnorte.es', 'Torneos Pádel Norte')
        && $correo->servidor === 'smtp.padelnorte.es');
});
