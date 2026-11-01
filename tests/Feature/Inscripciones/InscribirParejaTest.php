<?php

use App\Actions\Inscripciones\CambiarEstadoDeInscripcion;
use App\Actions\Inscripciones\FijarCabezaDeSerie;
use App\Actions\Inscripciones\InscribirPareja;
use App\Competicion\Errores\OperacionNoPermitida;
use App\Enums\EstadoInscripcion;
use App\Enums\EstadoParticipante;
use App\Enums\OrigenInscripcion;
use App\Enums\Rol;
use App\Models\Categoria;
use App\Models\Inscripcion;
use App\Models\Jugador;
use App\Models\Organizador;
use App\Models\RegistroDeCambio;
use App\Models\User;
use Database\Seeders\PlanesSeeder;

beforeEach(function () {
    $this->seed(PlanesSeeder::class);
    usarOrganizador(Organizador::factory()->create());

    $this->propietario = User::factory()->rol(Rol::Propietario)->create();
    $this->categoria = Categoria::factory()->create(['cupo' => 2]);
});

/** @return Inscripcion */
function inscribir(Categoria $categoria, ?User $quien = null, bool $confirmada = true): Inscripcion
{
    [$a, $b] = Jugador::factory()->count(2)->create()->all();

    return app(InscribirPareja::class)->handle($categoria, $a, $b, OrigenInscripcion::Manual, $quien, $confirmada);
}

test('una pareja nueva queda confirmada con sus dos jugadores y su nombre', function () {
    $garcia = Jugador::factory()->create(['nombre' => 'Ana', 'apellidos' => 'García']);
    $lopez = Jugador::factory()->create(['nombre' => 'Eva', 'apellidos' => 'López']);

    $inscripcion = app(InscribirPareja::class)->handle($this->categoria, $garcia, $lopez, OrigenInscripcion::Manual, $this->propietario);

    expect($inscripcion->estado)->toBe(EstadoInscripcion::Confirmada)
        ->and($inscripcion->confirmada_en)->not->toBeNull()
        ->and($inscripcion->registrada_por)->toBe($this->propietario->id)
        ->and($inscripcion->participante->nombre)->toBe('García / López')
        ->and($inscripcion->participante->idsDeJugadores())->toBe([$garcia->id, $lopez->id])
        ->and(RegistroDeCambio::query()->where('accion', 'inscribir')->count())->toBe(1);
});

test('al llenarse el cupo las siguientes parejas van a la lista de espera en orden', function () {
    inscribir($this->categoria);
    inscribir($this->categoria);
    $tercera = inscribir($this->categoria);
    $cuarta = inscribir($this->categoria);

    expect($tercera->estado)->toBe(EstadoInscripcion::ListaDeEspera)
        ->and($tercera->posicion_espera)->toBe(1)
        ->and($cuarta->posicion_espera)->toBe(2);
});

test('una pareja sin confirmar queda pendiente y no cuenta para el cupo', function () {
    $pendiente = inscribir($this->categoria, confirmada: false);
    $segunda = inscribir($this->categoria);
    $tercera = inscribir($this->categoria);

    expect($pendiente->estado)->toBe(EstadoInscripcion::Pendiente)
        ->and($segunda->estado)->toBe(EstadoInscripcion::Confirmada)
        ->and($tercera->estado)->toBe(EstadoInscripcion::Confirmada);
});

test('un jugador no se inscribe dos veces en la misma categoría ni consigo mismo', function () {
    [$a, $b, $c] = Jugador::factory()->count(3)->create()->all();
    app(InscribirPareja::class)->handle($this->categoria, $a, $b, OrigenInscripcion::Manual);

    expect(fn () => app(InscribirPareja::class)->handle($this->categoria, $a, $c, OrigenInscripcion::Manual))
        ->toThrow(OperacionNoPermitida::class, 'ya está inscrito');
    expect(fn () => app(InscribirPareja::class)->handle($this->categoria, $c, $c, OrigenInscripcion::Manual))
        ->toThrow(OperacionNoPermitida::class, 'dos jugadores distintos');

    // En otra categoría sí puede jugar.
    $otra = Categoria::factory()->create(['torneo_id' => $this->categoria->torneo_id]);
    expect(app(InscribirPareja::class)->handle($otra, $a, $c, OrigenInscripcion::Manual)->estado)->toBe(EstadoInscripcion::Confirmada);
});

test('la baja retira a la pareja y recoloca la lista de espera; confirmar al cupo no se permite', function () {
    $primera = inscribir($this->categoria);
    inscribir($this->categoria);
    $enEspera1 = inscribir($this->categoria);
    $enEspera2 = inscribir($this->categoria);

    expect(fn () => app(CambiarEstadoDeInscripcion::class)->handle($enEspera1, EstadoInscripcion::Confirmada))
        ->toThrow(OperacionNoPermitida::class, 'al cupo');

    app(CambiarEstadoDeInscripcion::class)->handle($primera, EstadoInscripcion::Baja, $this->propietario);

    expect($primera->refresh()->estado)->toBe(EstadoInscripcion::Baja)
        ->and($primera->baja_en)->not->toBeNull()
        ->and($primera->participante->estado)->toBe(EstadoParticipante::Retirado);

    // Ahora hay plaza: sube la primera en espera y la segunda pasa a ser la primera.
    app(CambiarEstadoDeInscripcion::class)->handle($enEspera1, EstadoInscripcion::Confirmada, $this->propietario);

    expect($enEspera1->refresh()->estado)->toBe(EstadoInscripcion::Confirmada)
        ->and($enEspera1->posicion_espera)->toBeNull()
        ->and($enEspera2->refresh()->posicion_espera)->toBe(1);

    // Y la baja se puede readmitir: queda pendiente, con el participante activo.
    app(CambiarEstadoDeInscripcion::class)->handle($primera, EstadoInscripcion::Pendiente, $this->propietario);
    expect($primera->refresh()->estado)->toBe(EstadoInscripcion::Pendiente)
        ->and($primera->baja_en)->toBeNull()
        ->and($primera->participante->estado)->toBe(EstadoParticipante::Activo);
});

test('el número de cabeza de serie no se repite en la categoría', function () {
    $primera = inscribir($this->categoria);
    $segunda = inscribir($this->categoria);

    app(FijarCabezaDeSerie::class)->handle($primera->participante, 1, $this->propietario);

    expect(fn () => app(FijarCabezaDeSerie::class)->handle($segunda->participante, 1, $this->propietario))
        ->toThrow(OperacionNoPermitida::class, 'Ya hay una cabeza de serie número 1');

    app(FijarCabezaDeSerie::class)->handle($segunda->participante, 2, $this->propietario);
    app(FijarCabezaDeSerie::class)->handle($primera->participante, null, $this->propietario);

    expect($primera->participante->refresh()->cabeza_de_serie)->toBeNull()
        ->and($segunda->participante->refresh()->cabeza_de_serie)->toBe(2);
});
