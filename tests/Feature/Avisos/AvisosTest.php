<?php

use App\Actions\Competicion\CambiarEstadoDelTorneo;
use App\Actions\Competicion\CerrarFase;
use App\Actions\Competicion\GenerarFranjas;
use App\Actions\Competicion\GenerarPartidos;
use App\Actions\Competicion\PlanificarHorarios;
use App\Actions\Competicion\RegistrarResultado;
use App\Actions\Competicion\SortearGrupos;
use App\Actions\Competicion\ValidarResultado;
use App\Actions\Inscripciones\CambiarEstadoDeInscripcion;
use App\Actions\Inscripciones\InscribirPareja;
use App\Competicion\Datos\Resultado;
use App\Competicion\Datos\Set;
use App\Enums\EstadoInscripcion;
use App\Enums\EstadoTorneo;
use App\Enums\OrigenInscripcion;
use App\Enums\ResultadosPorJugadores;
use App\Enums\Rol;
use App\Filament\Resources\Torneos\Pages\EditTorneo;
use App\Livewire\Publico\FichaDelTorneo;
use App\Livewire\Publico\PortalDelJugador;
use App\Models\Categoria;
use App\Models\Comunicacion;
use App\Models\Fase;
use App\Models\FormatoPartido;
use App\Models\Inscripcion;
use App\Models\Jugador;
use App\Models\Organizador;
use App\Models\Participante;
use App\Models\Partido;
use App\Models\Pista;
use App\Models\SuscripcionPush;
use App\Models\Torneo;
use App\Models\User;
use App\Notifications\AvisoDeLaOrganizacion;
use App\Notifications\CruceAsignado;
use App\Notifications\HorariosPublicados;
use App\Notifications\InscripcionConfirmada;
use App\Notifications\ProximoPartido;
use App\Notifications\ResultadoDelPartido;
use App\Notifications\ResultadoPorValidar;
use App\Push\CanalPush;
use App\Push\Remitente;
use App\Push\RemitenteDePruebas;
use Database\Seeders\PlanesSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PlanesSeeder::class);
    usarOrganizador(Organizador::factory()->create(['id' => 'norte']));
    config()->set('webpush.clave_publica', 'publica-de-pruebas');
    config()->set('webpush.clave_privada', 'privada-de-pruebas');
    $this->remitente = new RemitenteDePruebas;
    app()->instance(Remitente::class, $this->remitente);

    FormatoPartido::factory()->predeterminado()->create(['duracion_estimada' => 60]);
    $this->propietario = User::factory()->rol(Rol::Propietario)->create();
    $this->torneo = Torneo::factory()->enEstado(EstadoTorneo::Sorteo)->create(['nombre' => 'Open de Verano', 'slug' => 'open-verano', 'descanso_minimo_minutos' => 0]);
    $this->categoria = Categoria::factory()->create(['torneo_id' => $this->torneo->id, 'cupo' => 8]);
});

function suscripcionDe(?Jugador $jugador, string $endpoint = 'https://push.example.com/'): SuscripcionPush
{
    return SuscripcionPush::query()->create([
        'endpoint' => $endpoint.Str::random(8), 'endpoint_hash' => hash('sha256', $endpoint.Str::random(16)),
        'clave_p256dh' => 'p256', 'clave_auth' => 'auth', 'jugador_id' => $jugador?->id,
    ]);
}

/** 4 parejas confirmadas en un grupo, con su cuadro detrás; devuelve la fase de grupos. */
function torneoConGrupo(Torneo $torneo, Categoria $categoria, User $usuario): Fase
{
    foreach (range(1, 4) as $i) {
        Inscripcion::factory()->create(['participante_id' => Participante::factory()->create(['categoria_id' => $categoria->id])->id]);
    }
    $fase = Fase::factory()->grupos(['semilla' => 4, 'clasificados' => ['por_grupo' => 2, 'mejores_de_posicion' => null, 'cuantos_mejores' => 0]])->create(['categoria_id' => $categoria->id]);
    Fase::factory()->eliminatoria()->create(['categoria_id' => $categoria->id]);
    app(SortearGrupos::class)->handle($fase, $usuario);
    app(GenerarPartidos::class)->handle($fase, $usuario);

    return $fase;
}

test('una inscripción confirmada avisa a los dos jugadores por correo, push y portal, según lo que cada uno eligió', function () {
    $ana = Jugador::factory()->create();
    $eva = Jugador::factory()->create(['avisos' => ['correo' => false, 'push' => true]]);
    suscripcionDe($ana);

    $inscripcion = app(InscribirPareja::class)->handle($this->categoria, $ana, $eva, OrigenInscripcion::Manual, $this->propietario);

    // Push de verdad (por la cola síncrona) al dispositivo de Ana.
    expect($this->remitente->titulos())->toBe(['Inscripción confirmada'])
        ->and($ana->notifications()->count())->toBe(1)
        ->and($ana->notifications()->first()->data['titulo'])->toBe('Inscripción confirmada');

    $aviso = new InscripcionConfirmada($inscripcion);
    expect($aviso->via($ana))->toBe(['database', 'mail', CanalPush::class])
        ->and($aviso->via($eva))->toBe(['database', CanalPush::class]);

    // Al subir de la lista de espera, «¡Tenéis plaza!».
    Notification::fake();
    $otra = app(InscribirPareja::class)->handle($this->categoria, Jugador::factory()->create(), Jugador::factory()->create(), OrigenInscripcion::Manual, null, confirmada: false);
    app(CambiarEstadoDeInscripcion::class)->handle($otra, EstadoInscripcion::ListaDeEspera);
    app(CambiarEstadoDeInscripcion::class)->handle($otra->refresh(), EstadoInscripcion::Confirmada);
    Notification::assertSentTo($otra->participante->jugadores->first(), InscripcionConfirmada::class, fn (InscripcionConfirmada $n): bool => $n->desdeEspera && $n->titulo() === '¡Tenéis plaza!');
});

test('al publicar los horarios cada jugador recibe los suyos y los seguidores del torneo un push', function () {
    $fase = torneoConGrupo($this->torneo, $this->categoria, $this->propietario);
    $this->torneo->torneoPistas()->create(['pista_id' => Pista::factory()->create(['nombre' => 'Central'])->id, 'orden' => 1, 'disponibilidad' => [
        ['fecha' => $this->torneo->fecha_inicio->toDateString(), 'desde' => '09:00', 'hasta' => '21:00'],
    ]]);
    app(GenerarFranjas::class)->handle($this->torneo);
    app(PlanificarHorarios::class)->handle($this->torneo, $this->propietario);

    $seguidor = suscripcionDe(null);
    $seguidor->torneos()->attach($this->torneo->id);

    Notification::fake();
    app(CambiarEstadoDelTorneo::class)->handle($this->torneo, EstadoTorneo::EnJuego, $this->propietario);

    $jugador = Partido::query()->firstOrFail()->local->jugadores->first();
    Notification::assertSentTo($jugador, HorariosPublicados::class, fn (HorariosPublicados $n): bool => count($n->partidos) === 3 && str_contains($n->partidos[0], 'Central'));
    Notification::assertCount(8);
    expect($this->remitente->titulos())->toBe(['Horarios de Open de Verano']);

    // Volver atrás y otra vez a en juego no lo repite.
    app(CambiarEstadoDelTorneo::class)->handle($this->torneo, EstadoTorneo::Sorteo, $this->propietario);
    app(CambiarEstadoDelTorneo::class)->handle($this->torneo, EstadoTorneo::EnJuego, $this->propietario);
    Notification::assertCount(8);
    expect($fase->partidos()->count())->toBe(6);
});

test('el resultado de un jugador avisa al rival para validarlo, y el validado a las dos parejas', function () {
    $this->torneo->update(['resultados_por_jugadores' => ResultadosPorJugadores::ConValidacion, 'estado' => EstadoTorneo::EnJuego]);
    $fase = torneoConGrupo($this->torneo, $this->categoria, $this->propietario);
    $partido = $fase->partidos()->orderBy('id')->firstOrFail();
    $quienAnota = $partido->local->jugadores->first();

    Notification::fake();
    app(RegistrarResultado::class)->handle($partido, new Resultado([new Set(6, 2), new Set(6, 3)]), $quienAnota);

    Notification::assertSentTo($partido->visitante->jugadores, ResultadoPorValidar::class);
    Notification::assertNotSentTo($partido->local->jugadores, ResultadoPorValidar::class);

    app(ValidarResultado::class)->handle($partido->refresh(), $partido->visitante->jugadores->first());
    Notification::assertSentTo([...$partido->local->jugadores, ...$partido->visitante->jugadores], ResultadoDelPartido::class);
    expect((new ResultadoDelPartido($partido->refresh()))->titulo())->toBe('Resultado: 6-2 6-3');
});

test('al cerrar los grupos cada clasificado recibe su cruce del cuadro', function () {
    $fase = torneoConGrupo($this->torneo, $this->categoria, $this->propietario);
    foreach ($fase->partidos()->get() as $partido) {
        app(RegistrarResultado::class)->handle($partido, new Resultado([new Set(6, 1), new Set(6, 1)]), $this->propietario);
    }

    Notification::fake();
    $clasificados = app(CerrarFase::class)->handle($fase, $this->propietario);

    expect($clasificados)->toHaveCount(2);
    foreach ($clasificados as $clasificado) {
        Notification::assertSentTo(Participante::query()->findOrFail($clasificado->participante)->jugadores, CruceAsignado::class);
    }
    Notification::assertCount(4);
});

test('la organización manda un aviso a los inscritos de una categoría por los canales que elige', function () {
    torneoConGrupo($this->torneo, $this->categoria, $this->propietario);
    $otra = Categoria::factory()->create(['torneo_id' => $this->torneo->id]);
    Inscripcion::factory()->create(['participante_id' => Participante::factory()->create(['categoria_id' => $otra->id])->id]);

    Notification::fake();
    Filament::setCurrentPanel('panel');
    $this->actingAs($this->propietario);

    Livewire::test(EditTorneo::class, ['record' => $this->torneo->getRouteKey()])
        ->callAction('aviso', ['categoria_id' => $this->categoria->id, 'titulo' => 'Cambio de pista', 'cuerpo' => 'Mañana se juega en el club de al lado.', 'canales' => ['push']])
        ->assertHasNoActionErrors()
        ->assertNotified('Aviso enviado');

    $comunicacion = Comunicacion::query()->sole();
    expect($comunicacion->destinatarios)->toBe(8)->and($comunicacion->canales)->toBe(['push']);

    $jugador = Participante::query()->where('categoria_id', $this->categoria->id)->firstOrFail()->jugadores->first();
    Notification::assertSentTo($jugador, AvisoDeLaOrganizacion::class, fn (AvisoDeLaOrganizacion $n): bool => $n->via($jugador) === ['database', CanalPush::class]);
    Notification::assertNotSentTo(Participante::query()->where('categoria_id', $otra->id)->firstOrFail()->jugadores, AvisoDeLaOrganizacion::class);
});

test('media hora antes de cada partido, un aviso a sus cuatro jugadores, una sola vez', function () {
    $this->torneo->update(['estado' => EstadoTorneo::EnJuego, 'horarios_publicados_en' => now()]);
    $fase = torneoConGrupo($this->torneo, $this->categoria, $this->propietario);
    $pista = Pista::factory()->create();
    $partido = $fase->partidos()->orderBy('id')->firstOrFail();
    $franja = $this->torneo->franjas()->create(['pista_id' => $pista->id, 'inicio' => now()->addMinutes(20), 'fin' => now()->addMinutes(80)]);
    $partido->update(['franja_id' => $franja->id, 'pista_id' => $pista->id, 'estado' => 'programado']);
    $lejano = $fase->partidos()->whereKeyNot($partido->id)->firstOrFail();
    $franja2 = $this->torneo->franjas()->create(['pista_id' => $pista->id, 'inicio' => now()->addHours(3), 'fin' => now()->addHours(4)]);
    $lejano->update(['franja_id' => $franja2->id, 'pista_id' => $pista->id, 'estado' => 'programado']);

    Notification::fake();
    $this->artisan('avisos:proximos-partidos')->expectsOutputToContain('Partidos avisados: 1')->assertSuccessful();
    Notification::assertSentTo([...$partido->local->jugadores, ...$partido->visitante->jugadores], ProximoPartido::class);
    Notification::assertCount(4);

    $this->artisan('avisos:proximos-partidos')->expectsOutputToContain('Partidos avisados: 0');
    expect($partido->refresh()->aviso_proximo_en)->not->toBeNull();
});

test('el jugador activa el push y elige sus avisos; cualquiera sigue un torneo sin cuenta', function () {
    $ana = Jugador::factory()->create();
    $suscripcion = ['endpoint' => 'https://push.example.com/ana', 'keys' => ['p256dh' => 'clave', 'auth' => 'secreto']];

    $this->actingAs($ana, 'jugador');
    Livewire::test(PortalDelJugador::class)
        ->call('guardarPush', $suscripcion, 'Android')
        ->set('avisosCorreo', false)
        ->call('guardarAvisos')
        ->assertSet('aviso', 'Avisos guardados.');

    expect(SuscripcionPush::query()->sole()->jugador_id)->toBe($ana->id)
        ->and($ana->refresh()->avisos)->toBe(['correo' => false, 'push' => true]);

    Livewire::test(PortalDelJugador::class)->call('guardarPush', ['endpoint' => 'http://inseguro'], null)->assertHasErrors('portal');

    // Un visitante sin cuenta sigue el torneo desde la ficha.
    auth('jugador')->logout();
    $this->torneo->update(['estado' => EstadoTorneo::InscripcionAbierta]);
    $ficha = Livewire::test(FichaDelTorneo::class, ['torneo' => $this->torneo])
        ->call('guardarPush', ['endpoint' => 'https://push.example.com/anonimo', 'keys' => ['p256dh' => 'a', 'auth' => 'b']], 'iPhone');

    $anonimo = SuscripcionPush::query()->whereNull('jugador_id')->sole();
    expect($anonimo->torneos()->pluck('torneos.id')->all())->toBe([$this->torneo->id])
        ->and($ficha->instance()->sigue('https://push.example.com/anonimo'))->toBeTrue();

    $ficha->call('quitarPush', 'https://push.example.com/anonimo');
    expect($anonimo->torneos()->count())->toBe(0);

    // Y el portal le quita su dispositivo.
    $this->actingAs($ana, 'jugador');
    Livewire::test(PortalDelJugador::class)->call('quitarPush', 'https://push.example.com/ana');
    expect(SuscripcionPush::query()->where('jugador_id', $ana->id)->count())->toBe(0);
});

test('push:claves genera un par de claves VAPID', function () {
    config()->set('webpush.clave_publica', null);
    $this->artisan('push:claves')->expectsOutputToContain('WEBPUSH_VAPID_PUBLIC_KEY=')->assertSuccessful();
});
