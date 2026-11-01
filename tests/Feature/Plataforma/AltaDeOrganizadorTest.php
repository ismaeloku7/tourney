<?php

use App\Actions\Plataforma\VerificarOrganizador;
use App\Enums\EstadoOrganizador;
use App\Enums\Rol;
use App\Enums\TipoDeEventoDeUso;
use App\Livewire\AltaDeOrganizador;
use App\Mail\VerificarCorreoDelOrganizador;
use App\Models\EventoUso;
use App\Models\Organizador;
use App\Models\User;
use Database\Seeders\PlanesSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PlanesSeeder::class);
    Mail::fake();
});

test('la landing lleva al alta y el alta crea la organización pendiente con su propietario y manda el correo', function () {
    $this->get('http://saas.test/')->assertOk()->assertSee('Crear mi organización');
    $this->get('http://saas.test/alta')->assertOk()->assertSee('Dirección');

    Livewire::test(AltaDeOrganizador::class)
        ->set('nombre', 'Club Pádel Norte')
        ->assertSet('slug', 'club-padel-norte')
        ->set('slug', 'Norte')
        ->set('responsable_nombre', 'Marta Gil')
        ->set('email', 'Marta@Example.com')
        ->set('clave', 'una-clave-muy-larga')
        ->set('clave_confirmation', 'una-clave-muy-larga')
        ->set('privacidad', true)
        ->call('enviar')
        ->assertHasNoErrors()
        ->assertSet('enviada', true)
        ->assertSee('Revisa tu correo');

    $organizador = Organizador::query()->findOrFail('norte');

    expect($organizador->estado)->toBe(EstadoOrganizador::Pendiente)
        ->and($organizador->verificado_en)->toBeNull()
        ->and($organizador->verificacion_token)->not->toBeNull()
        ->and($organizador->verificacion_expira_en?->isFuture())->toBeTrue()
        ->and($organizador->responsable_email)->toBe('marta@example.com')
        ->and($organizador->plan->clave)->toBe('gratis');

    $propietario = comoOrganizador($organizador, fn () => User::query()->sole());
    expect($propietario->rol)->toBe(Rol::Propietario)->and(Hash::check('una-clave-muy-larga', $propietario->password))->toBeTrue();

    Mail::assertSent(VerificarCorreoDelOrganizador::class, fn (VerificarCorreoDelOrganizador $correo): bool => $correo->hasTo('marta@example.com') && $correo->organizador->is($organizador));

    // Pendiente: sus direcciones aún no responden.
    $this->get('http://norte.saas.test/')->assertNotFound();
});

test('el enlace del correo activa la organización; uno falso o caducado, no', function () {
    Livewire::test(AltaDeOrganizador::class)
        ->set(['nombre' => 'Club Sur', 'slug' => 'sur', 'responsable_nombre' => 'Luis', 'email' => 'luis@example.com', 'clave' => 'una-clave-muy-larga', 'clave_confirmation' => 'una-clave-muy-larga', 'privacidad' => true])
        ->call('enviar')
        ->assertHasNoErrors();

    $token = null;
    Mail::assertSent(VerificarCorreoDelOrganizador::class, function (VerificarCorreoDelOrganizador $correo) use (&$token): bool {
        $token = $correo->token;

        return true;
    });

    $this->get('http://saas.test/alta/verificar/sur/token-falso')->assertOk()->assertSee('Este enlace no vale');
    expect(Organizador::query()->findOrFail('sur')->estado)->toBe(EstadoOrganizador::Pendiente);

    $this->get("http://saas.test/alta/verificar/sur/{$token}")->assertOk()->assertSee('Club Sur ya está activa')->assertSee('http://sur.saas.test/panel');

    $organizador = Organizador::query()->findOrFail('sur');
    expect($organizador->estado)->toBe(EstadoOrganizador::Activo)
        ->and($organizador->verificado_en)->not->toBeNull()
        ->and($organizador->verificacion_token)->toBeNull()
        ->and(EventoUso::query()->where('organizador_id', 'sur')->where('tipo', TipoDeEventoDeUso::OrganizadorVerificado->value)->exists())->toBeTrue();

    $this->get('http://sur.saas.test/')->assertOk()->assertSee('Club Sur');

    // Caducado.
    $otro = Organizador::factory()->pendiente()->create(['id' => 'tarde', 'verificacion_token' => hash('sha256', 'abc'), 'verificacion_expira_en' => now()->subHour()]);
    expect(app(VerificarOrganizador::class)->handle($otro, 'abc'))->toBeNull()
        ->and($otro->refresh()->estado)->toBe(EstadoOrganizador::Pendiente);
});

test('el alta valida la dirección, la contraseña y la privacidad, y un robot no crea nada', function () {
    Organizador::factory()->create(['id' => 'cogido']);

    Livewire::test(AltaDeOrganizador::class)
        ->set(['nombre' => 'X', 'slug' => 'bien', 'responsable_nombre' => 'A', 'email' => 'no-es-correo', 'clave' => 'corta', 'clave_confirmation' => 'corta', 'privacidad' => false])
        ->call('enviar')
        ->assertHasErrors(['nombre', 'email', 'clave', 'privacidad']);

    // La dirección cogida la rechaza el alta, no el formulario.
    Livewire::test(AltaDeOrganizador::class)
        ->set(['nombre' => 'Otro club', 'slug' => 'cogido', 'responsable_nombre' => 'A', 'email' => 'a@example.com', 'clave' => 'una-clave-muy-larga', 'clave_confirmation' => 'una-clave-muy-larga', 'privacidad' => true])
        ->call('enviar')
        ->assertHasErrors(['slug']);

    Livewire::test(AltaDeOrganizador::class)
        ->set(['nombre' => 'Club Robot', 'slug' => 'robot', 'responsable_nombre' => 'R', 'email' => 'r@example.com', 'clave' => 'una-clave-muy-larga', 'clave_confirmation' => 'una-clave-muy-larga', 'privacidad' => true, 'sitio_web' => 'http://spam'])
        ->call('enviar')
        ->assertSet('enviada', true);

    expect(Organizador::query()->find('robot'))->toBeNull();
    Mail::assertNothingSent();

    // Un slug reservado tampoco.
    Livewire::test(AltaDeOrganizador::class)
        ->set(['nombre' => 'Admin', 'slug' => 'admin', 'responsable_nombre' => 'A', 'email' => 'a@example.com', 'clave' => 'una-clave-muy-larga', 'clave_confirmation' => 'una-clave-muy-larga', 'privacidad' => true])
        ->call('enviar')
        ->assertHasErrors(['slug']);
});

test('las páginas legales de la plataforma dicen si faltan los datos del titular', function () {
    config()->set('saas.titular', ['nombre' => null, 'nif' => null, 'domicilio' => null, 'email' => null]);
    $this->get('http://saas.test/legal/aviso')->assertOk()->assertSee('todavía no ha publicado los datos de su titular');

    config()->set('saas.titular', ['nombre' => 'Tourney SL', 'nif' => 'B12345678', 'domicilio' => 'Calle Mayor 1', 'email' => 'legal@example.com']);
    $this->get('http://saas.test/legal/privacidad')->assertOk()->assertSee('Tourney SL')->assertSee('acceso, rectificación');
});
