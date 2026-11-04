<?php

use App\Actions\Inscripciones\MoverPareja;
use App\Competicion\Errores\OperacionNoPermitida;
use App\Enums\EstadoInscripcion;
use App\Enums\Rol;
use App\Filament\Resources\Parejas\Pages\ListParejas;
use App\Models\Categoria;
use App\Models\Fase;
use App\Models\Grupo;
use App\Models\Inscripcion;
use App\Models\Jugador;
use App\Models\Organizador;
use App\Models\Participante;
use App\Models\RegistroDeCambio;
use App\Models\Torneo;
use App\Models\User;
use Database\Seeders\PlanesSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

/** Las parejas femeninas del Rally pasan a un torneo Femenino. */
beforeEach(function () {
    $this->seed(PlanesSeeder::class);
    usarOrganizador(Organizador::factory()->create());
    Filament::setCurrentPanel('panel');
    $this->actingAs(User::factory()->rol(Rol::Propietario)->create());

    $this->iniciacion = Categoria::factory()->create(['torneo_id' => Torneo::factory()->create(['nombre' => 'Rally 2026'])->id, 'nombre' => 'Iniciación']);
    $this->femenino = Categoria::factory()->create(['torneo_id' => Torneo::factory()->create(['nombre' => 'Femenino'])->id, 'nombre' => 'Femenino Iniciación', 'cupo' => 1]);

    $this->ana = Inscripcion::factory()->create(['participante_id' => Participante::factory()->create(['categoria_id' => $this->iniciacion->id])->id]);
    $this->eva = Inscripcion::factory()->create(['participante_id' => Participante::factory()->create(['categoria_id' => $this->iniciacion->id])->id]);
    $this->luis = Inscripcion::factory()->create(['participante_id' => Participante::factory()->create(['categoria_id' => $this->iniciacion->id])->id]);
});

test('las parejas marcadas pasan a otro torneo con sus jugadores; la que no cabe, a la lista de espera', function () {
    Livewire::test(ListParejas::class)
        ->selectTableRecords([$this->ana->id, $this->eva->id])
        ->callAction(TestAction::make('moverMarcadas')->table()->bulk(), ['categoria_id' => $this->femenino->id])
        ->assertHasNoActionErrors()
        ->assertNotified('2 parejas movidas a Femenino · Femenino Iniciación');

    $ana = $this->ana->refresh();
    $eva = $this->eva->refresh();
    expect($ana->categoria_id)->toBe($this->femenino->id)
        ->and($ana->participante->categoria_id)->toBe($this->femenino->id)
        ->and($ana->participante->jugadores()->count())->toBe(2)
        ->and($ana->estado)->toBe(EstadoInscripcion::Confirmada)
        ->and($eva->estado)->toBe(EstadoInscripcion::ListaDeEspera)
        ->and($eva->posicion_espera)->toBe(1)
        ->and($this->luis->refresh()->categoria_id)->toBe($this->iniciacion->id)
        ->and(RegistroDeCambio::query()->where('accion', 'mover_pareja')->count())->toBe(2);
});

test('una pareja suelta se mueve desde su fila', function () {
    Livewire::test(ListParejas::class)
        ->callAction(TestAction::make('mover')->table($this->luis), ['categoria_id' => $this->femenino->id])
        ->assertHasNoActionErrors()
        ->assertNotified('Pareja movida a Femenino · Femenino Iniciación');

    expect($this->luis->refresh()->categoria_id)->toBe($this->femenino->id);
});

test('no se mueve una pareja ya sorteada ni a una categoría donde ya juega uno de sus jugadores', function () {
    $grupo = Grupo::factory()->create(['fase_id' => Fase::factory()->create(['categoria_id' => $this->iniciacion->id])->id]);
    $this->ana->participante->grupos()->attach($grupo, ['posicion_sorteo' => 1]);

    expect(fn () => app(MoverPareja::class)->handle($this->ana->refresh(), $this->femenino))->toThrow(OperacionNoPermitida::class, 'ya está en el sorteo');

    $jugador = $this->eva->participante->jugadores()->first();
    Inscripcion::factory()->create(['participante_id' => Participante::factory()->conJugadores($jugador, Jugador::factory()->create())->create(['categoria_id' => $this->femenino->id])->id]);
    expect(fn () => app(MoverPareja::class)->handle($this->eva, $this->femenino))->toThrow(OperacionNoPermitida::class, 'ya está inscrito');

    Livewire::test(ListParejas::class)
        ->selectTableRecords([$this->ana->id, $this->eva->id])
        ->callAction(TestAction::make('moverMarcadas')->table()->bulk(), ['categoria_id' => $this->femenino->id])
        ->assertNotified('2 no se han movido');

    expect($this->ana->refresh()->categoria_id)->toBe($this->iniciacion->id)
        ->and($this->eva->refresh()->categoria_id)->toBe($this->iniciacion->id);
});
