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
        Ajuste::CorreoRemitente->value => 'torneos@padelnorte.es',
        Ajuste::CorreoRemitenteNombre->value => 'Torneos Pádel Norte',
    ];
}

/** Guarda el servidor del club y le pone la contraseña desde su ventana. */
function configurarServidorDelClub(): void
{
    Livewire::test(Ajustes::class)->fillForm(servidorDelClub())->call('guardar')->assertHasNoFormErrors();
    Livewire::test(Ajustes::class)->callAction('claveCorreo', ['clave' => 'clave-secreta'])->assertHasNoActionErrors();
}

test('la contraseña va en su propia ventana: se guarda cifrada, no está en el formulario ni en el registro', function () {
    // Sin servidor propio no hay botón de contraseña.
    Livewire::test(Ajustes::class)->assertActionHidden('claveCorreo');

    Livewire::test(Ajustes::class)
        ->fillForm(servidorDelClub())
        ->call('guardar')
        ->assertHasNoFormErrors()
        ->assertNotified('Ajustes guardados')
        ->assertSee('Contraseña: sin poner');

    // El formulario no lleva ningún campo de contraseña que el navegador pueda rellenar.
    Livewire::test(Ajustes::class)
        ->assertDontSeeHtml('type="password"')
        ->assertActionVisible('claveCorreo')
        ->callAction('claveCorreo', ['clave' => 'clave-secreta'])
        ->assertHasNoActionErrors()
        ->assertNotified('Contraseña del correo guardada');

    $guardada = ValorDeAjuste::obtener(Ajuste::CorreoClave);
    expect($guardada)->toBeString()->not->toBe('clave-secreta')
        ->and(Crypt::decryptString((string) $guardada))->toBe('clave-secreta')
        ->and(ValorDeAjuste::obtener(Ajuste::CorreoPuerto))->toBe(465)
        ->and(RegistroDeCambio::query()->where('accion', 'ajustes')->get()->map(fn (RegistroDeCambio $r): string => (string) json_encode($r->getAttributes()))->implode(' '))->not->toContain('clave-secreta')
        ->and(RegistroDeCambio::query()->where('accion', 'ajustes')->latest('id')->first()?->despues)->toBe([Ajuste::CorreoClave->value => 'cambiada']);

    // Guardar el resto de ajustes no la toca.
    Livewire::test(Ajustes::class)
        ->assertSee('Contraseña: guardada')
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

    configurarServidorDelClub();

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

test('el correo de prueba se manda a la dirección que se elija', function () {
    Mail::fake();

    Livewire::test(Ajustes::class)
        ->fillForm(servidorDelClub())
        ->call('probarCorreo', 'socio@gmail.com')
        ->assertNotified('Correo de prueba enviado');

    Mail::assertSent(CorreoDePrueba::class, fn (CorreoDePrueba $correo): bool => $correo->hasTo('socio@gmail.com')
        && ! $correo->hasTo('duena@padelnorte.es')
        && $correo->hasFrom('torneos@padelnorte.es', 'Torneos Pádel Norte'));

    // Una dirección mal escrita no manda nada.
    Livewire::test(Ajustes::class)->call('probarCorreo', 'socio@')->assertNotified('Esa dirección no es válida');
    Mail::assertSentCount(1);
});

test('sin servidor propio, el correo de prueba sale por el de la plataforma', function () {
    Mail::fake();

    Livewire::test(Ajustes::class)
        ->fillForm([Ajuste::NombrePublico->value => 'Pádel Norte', Ajuste::CorreoPropio->value => false])
        ->call('probarCorreo', 'socio@hotmail.com')
        ->assertNotified('Correo de prueba enviado');

    Mail::assertSent(CorreoDePrueba::class, fn (CorreoDePrueba $correo): bool => $correo->hasTo('socio@hotmail.com')
        && $correo->servidor === 'el de la plataforma'
        && $correo->hasFrom((string) config('mail.from.address')));
});
