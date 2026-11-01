<?php

use App\Enums\EstadoInscripcion;
use App\Enums\EstadoTorneo;
use App\Enums\Rol;
use App\Filament\Pages\Panel;
use App\Models\Categoria;
use App\Models\Inscripcion;
use App\Models\Organizador;
use App\Models\Participante;
use App\Models\Torneo;
use App\Models\User;
use Database\Seeders\PlanesSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

/**
 * El inicio del panel: la organización de un vistazo y lo que espera una
 * decisión, con un torneo que empieza en diez días.
 */
beforeEach(function () {
    $this->seed(PlanesSeeder::class);
    usarOrganizador(Organizador::factory()->create(['id' => 'norte']));
    Filament::setCurrentPanel('panel');

    $this->propietario = User::factory()->rol(Rol::Propietario)->create(['nombre' => 'Pablo']);
    $this->torneo = Torneo::factory()->conInscripcionAbierta()->create([
        'nombre' => 'Rally 2026',
        'fecha_inicio' => today()->addDays(10)->toDateString(),
        'fecha_fin' => today()->addDays(11)->toDateString(),
    ]);
    $this->categoria = Categoria::factory()->create(['torneo_id' => $this->torneo->id, 'nombre' => 'Iniciación', 'cupo' => 16]);
    $this->pendiente = Inscripcion::factory()->create([
        'participante_id' => Participante::factory()->create(['categoria_id' => $this->categoria->id])->id,
        'estado' => EstadoInscripcion::Pendiente,
    ]);
    Inscripcion::factory()->create(['participante_id' => Participante::factory()->create(['categoria_id' => $this->categoria->id])->id]);
});

test('el inicio enseña los torneos, el próximo, las plazas y lo que hay por revisar', function () {
    $this->actingAs($this->propietario);

    Livewire::test(Panel::class)
        ->assertOk()
        ->assertSee('Organización')
        ->assertSee('Competición')
        ->assertSee('Rally 2026')
        ->assertSee('Con la inscripción abierta')
        ->assertSee('1 pareja pendiente de confirmar')
        ->assertSee('Por revisar')
        ->assertSee($this->pendiente->participante->nombre)
        ->assertSee('pide plaza en')
        ->assertSee('1/16')
        ->assertSee('Crear torneo');
});

test('desde el inicio se confirma o se da de baja una pareja pendiente', function () {
    $this->actingAs($this->propietario);
    $otra = Inscripcion::factory()->create([
        'participante_id' => Participante::factory()->create(['categoria_id' => $this->categoria->id])->id,
        'estado' => EstadoInscripcion::Pendiente,
    ]);

    Livewire::test(Panel::class)
        ->call('confirmarInscripcion', $this->pendiente->id)
        ->assertNotified('Pareja confirmada')
        ->call('darDeBaja', $otra->id)
        ->assertNotified('Pareja dada de baja')
        ->assertSee('Nada pendiente');

    expect($this->pendiente->refresh()->estado)->toBe(EstadoInscripcion::Confirmada)
        ->and($otra->refresh()->estado)->toBe(EstadoInscripcion::Baja);
});

test('los días de «Partidos del día» se recorren con las flechas', function () {
    $this->actingAs($this->propietario);

    Livewire::test(Panel::class)
        ->assertSee('(hoy)')
        ->call('diaSiguiente')
        ->assertSee('(mañana)')
        ->call('diaAnterior')
        ->call('diaAnterior')
        ->assertSee('(ayer)');
});

test('un administrador solo ve y toca lo de sus torneos', function () {
    $administrador = User::factory()->rol(Rol::Administrador)->create();
    $suyo = Torneo::factory()->create(['nombre' => 'Open de Otoño', 'estado' => EstadoTorneo::EnJuego, 'fecha_inicio' => today()->toDateString(), 'fecha_fin' => today()->addDay()->toDateString()]);
    $suyo->administradores()->attach($administrador);
    $this->actingAs($administrador);

    Livewire::test(Panel::class)
        ->assertSee('Open de Otoño')
        ->assertDontSee($this->pendiente->participante->nombre)
        ->assertDontSee('Crear torneo');

    Livewire::test(Panel::class)->call('confirmarInscripcion', $this->pendiente->id)->assertNotFound();
    expect($this->pendiente->refresh()->estado)->toBe(EstadoInscripcion::Pendiente);
});
