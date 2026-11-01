<?php

use App\Enums\EstadoInscripcion;
use App\Enums\EstadoTorneo;
use App\Enums\OrigenInscripcion;
use App\Livewire\Publico\InscripcionEnTorneo;
use App\Livewire\Publico\InvitacionDePareja as PaginaDeInvitacion;
use App\Mail\EnlaceDeAcceso;
use App\Mail\InvitacionDePareja;
use App\Models\Categoria;
use App\Models\Inscripcion;
use App\Models\Jugador;
use App\Models\Organizador;
use App\Models\Plan;
use App\Models\Torneo;
use Database\Seeders\PlanesSeeder;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PlanesSeeder::class);
    Mail::fake();
    usarOrganizador(Organizador::factory()->create(['id' => 'norte']));
    $this->torneo = Torneo::factory()->conInscripcionAbierta()->create(['nombre' => 'Open de Invierno', 'slug' => 'open-invierno']);
    $this->categoria = Categoria::factory()->create(['torneo_id' => $this->torneo->id, 'nombre' => 'Mixta B', 'cupo' => 1]);
});

function tokenDeLaInvitacion(): string
{
    $enlace = null;
    Mail::assertSent(InvitacionDePareja::class, function (InvitacionDePareja $correo) use (&$enlace): bool {
        $enlace = $correo->enlace;

        return true;
    });
    preg_match('#/invitacion/([A-Za-z0-9]{64})#', (string) $enlace, $m);

    return $m[1];
}

test('un jugador nuevo se registra, inscribe a un compañero nuevo y queda pendiente hasta que confirma', function () {
    $this->get('http://norte.saas.test/torneos/open-invierno')->assertSee('Inscribirnos');

    $componente = Livewire::test(InscripcionEnTorneo::class, ['torneo' => $this->torneo])
        ->set(['email' => 'ana@example.com', 'nombre' => 'Ana', 'apellidos' => 'García', 'privacidad' => true])
        ->call('identificarme')
        ->assertHasNoErrors();

    $ana = Jugador::query()->where('email', 'ana@example.com')->sole();
    expect($ana->consentimiento_en)->not->toBeNull();
    $this->assertAuthenticatedAs($ana, 'jugador');

    $componente->set(['categoria_id' => $this->categoria->id, 'companero_email' => 'eva@example.com', 'companero_nombre' => 'Eva', 'companero_apellidos' => 'López'])
        ->call('inscribirme')
        ->assertHasNoErrors()
        ->assertSee('Inscripción enviada');

    $inscripcion = Inscripcion::query()->sole();
    $eva = Jugador::query()->where('email', 'eva@example.com')->sole();

    expect($inscripcion->estado)->toBe(EstadoInscripcion::Pendiente)
        ->and($inscripcion->origen)->toBe(OrigenInscripcion::Online)
        ->and($inscripcion->participante->nombre)->toBe('García / López')
        ->and($eva->consentimiento_en)->toBeNull()
        ->and($inscripcion->participante->participanteJugadores()->where('jugador_id', $eva->id)->sole()->confirmado_en)->toBeNull();

    Mail::assertSent(InvitacionDePareja::class, fn (InvitacionDePareja $c): bool => $c->hasTo('eva@example.com') && $c->quienInvita->is($ana));
    $token = tokenDeLaInvitacion();

    // Eva confirma: la pareja ocupa la única plaza y Eva entra en su portal.
    auth('jugador')->logout();
    Livewire::test(PaginaDeInvitacion::class, ['token' => $token])
        ->assertSee('te ha inscrito como su pareja')
        ->call('confirmar')
        ->assertHasErrors('privacidad')
        ->set('privacidad', true)
        ->call('confirmar')
        ->assertSee('¡Hecho!');

    expect($inscripcion->refresh()->estado)->toBe(EstadoInscripcion::Confirmada)
        ->and($eva->refresh()->consentimiento_en)->not->toBeNull();
    $this->assertAuthenticatedAs($eva, 'jugador');

    // La invitación ya no vale.
    Livewire::test(PaginaDeInvitacion::class, ['token' => $token])->assertSee('Esta invitación ya no vale');
});

test('con la plaza ya ocupada, la pareja que confirma va a la lista de espera', function () {
    [$a, $b, $c, $d] = Jugador::factory()->count(4)->create()->all();
    $this->actingAs($a, 'jugador');

    Livewire::test(InscripcionEnTorneo::class, ['torneo' => $this->torneo])
        ->set(['categoria_id' => $this->categoria->id, 'companero_email' => $b->getAttribute('email')])
        ->call('inscribirme')->assertHasNoErrors();
    $primera = tokenDeLaInvitacion();

    Mail::fake();
    $this->actingAs($c, 'jugador');
    Livewire::test(InscripcionEnTorneo::class, ['torneo' => $this->torneo])
        ->set(['categoria_id' => $this->categoria->id, 'companero_email' => $d->getAttribute('email')])
        ->call('inscribirme')->assertHasNoErrors();
    $segunda = tokenDeLaInvitacion();

    // Confirma antes la segunda: se lleva la plaza; la primera, a la espera.
    Livewire::test(PaginaDeInvitacion::class, ['token' => $segunda])->call('confirmar');
    Livewire::test(PaginaDeInvitacion::class, ['token' => $primera])->call('confirmar');

    $estados = Inscripcion::query()->orderBy('id')->pluck('estado')->all();
    expect($estados)->toBe([EstadoInscripcion::ListaDeEspera, EstadoInscripcion::Confirmada]);
});

test('un correo que ya es de un jugador recibe el enlace en vez de crear otra cuenta', function () {
    Jugador::factory()->create(['email' => 'ana@example.com']);

    Livewire::test(InscripcionEnTorneo::class, ['torneo' => $this->torneo])
        ->set(['email' => 'ana@example.com'])
        ->call('identificarme')
        ->assertSet('enlaceEnviado', true);

    expect(Jugador::query()->where('email', 'ana@example.com')->count())->toBe(1);
    Mail::assertSent(EnlaceDeAcceso::class, fn (EnlaceDeAcceso $c): bool => str_contains($c->enlace, 'destino=%2Ftorneos%2Fopen-invierno%2Finscripcion'));
    $this->assertGuest('jugador');
});

test('no se inscribe con la inscripción cerrada, contigo mismo, dos veces ni por encima del plan', function () {
    $ana = Jugador::factory()->create(['email' => 'ana@example.com']);
    $eva = Jugador::factory()->create(['email' => 'eva@example.com']);
    $this->actingAs($ana, 'jugador');

    Livewire::test(InscripcionEnTorneo::class, ['torneo' => $this->torneo])
        ->set(['categoria_id' => $this->categoria->id, 'companero_email' => 'ana@example.com'])
        ->call('inscribirme')->assertHasErrors('inscripcion');

    Livewire::test(InscripcionEnTorneo::class, ['torneo' => $this->torneo])
        ->set(['categoria_id' => $this->categoria->id, 'companero_email' => 'nuevo@example.com'])
        ->call('inscribirme')->assertHasErrors('inscripcion')->assertSee('escribe su nombre y sus apellidos');

    Livewire::test(InscripcionEnTorneo::class, ['torneo' => $this->torneo])
        ->set(['categoria_id' => $this->categoria->id, 'companero_email' => 'eva@example.com'])
        ->call('inscribirme')->assertHasNoErrors();
    Livewire::test(InscripcionEnTorneo::class, ['torneo' => $this->torneo])
        ->set(['categoria_id' => $this->categoria->id, 'companero_email' => 'eva@example.com'])
        ->call('inscribirme')->assertHasErrors('inscripcion')->assertSee('ya está inscrito');

    // Límite del plan: una pareja.
    Plan::porClave(Plan::GRATIS)->update(['parejas_por_torneo' => 1]);
    $otra = Categoria::factory()->create(['torneo_id' => $this->torneo->id]);
    $this->actingAs(Jugador::factory()->create(), 'jugador');
    Livewire::test(InscripcionEnTorneo::class, ['torneo' => $this->torneo])
        ->set(['categoria_id' => $otra->id, 'companero_email' => 'otro@example.com', 'companero_nombre' => 'Luis', 'companero_apellidos' => 'Pérez'])
        ->call('inscribirme')->assertHasErrors('inscripcion')->assertSee('máximo de parejas');

    // Inscripción cerrada.
    $this->torneo->update(['estado' => EstadoTorneo::InscripcionCerrada]);
    Livewire::test(InscripcionEnTorneo::class, ['torneo' => $this->torneo])->assertSee('no está abierta');

    expect(Inscripcion::query()->count())->toBe(1);
});
