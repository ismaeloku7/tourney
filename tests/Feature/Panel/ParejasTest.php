<?php

use App\Enums\Rol;
use App\Filament\Resources\Parejas\Pages\ListParejas;
use App\Models\Categoria;
use App\Models\Inscripcion;
use App\Models\Jugador;
use App\Models\Organizador;
use App\Models\Participante;
use App\Models\Torneo;
use App\Models\User;
use Database\Seeders\PlanesSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

/** El apartado Parejas: las de todos los torneos, en un solo sitio. */
beforeEach(function () {
    $this->seed(PlanesSeeder::class);
    usarOrganizador(Organizador::factory()->create(['id' => 'norte']));
    Filament::setCurrentPanel('panel');

    $this->rally = Torneo::factory()->create(['nombre' => 'Rally 2026']);
    $this->open = Torneo::factory()->create(['nombre' => 'Open de Otoño']);
    $iniciacion = Categoria::factory()->create(['torneo_id' => $this->rally->id, 'nombre' => 'Iniciación']);
    $mixta = Categoria::factory()->create(['torneo_id' => $this->open->id, 'nombre' => 'Mixta B']);

    $this->ana = Jugador::factory()->create(['nombre' => 'Ana', 'apellidos' => 'Zubiri', 'nivel_declarado' => '3']);
    $this->delRally = Inscripcion::factory()->create(['participante_id' => Participante::factory()->conJugadores($this->ana, Jugador::factory()->create(['nivel_declarado' => '2']))->create(['categoria_id' => $iniciacion->id])->id]);
    $this->delOpen = Inscripcion::factory()->create(['participante_id' => Participante::factory()->create(['categoria_id' => $mixta->id])->id]);
});

test('salen las parejas de todos los torneos, con su torneo, nivel y media, y se buscan por jugador', function () {
    $this->actingAs(User::factory()->rol(Rol::Propietario)->create());

    Livewire::test(ListParejas::class)
        ->assertCanSeeTableRecords([$this->delRally, $this->delOpen])
        ->assertSee('Rally 2026')
        ->assertSee('Open de Otoño')
        ->assertSee('Media 2,5')
        ->searchTable('Zubiri')
        ->assertCanSeeTableRecords([$this->delRally])
        ->assertCanNotSeeTableRecords([$this->delOpen])
        ->searchTable('')
        ->filterTable('torneo', $this->open->id)
        ->assertCanSeeTableRecords([$this->delOpen])
        ->assertCanNotSeeTableRecords([$this->delRally]);
});

test('un administrador solo ve las parejas de sus torneos', function () {
    $administrador = User::factory()->rol(Rol::Administrador)->create();
    $this->open->administradores()->attach($administrador);
    $this->actingAs($administrador);

    Livewire::test(ListParejas::class)
        ->assertCanSeeTableRecords([$this->delOpen])
        ->assertCanNotSeeTableRecords([$this->delRally]);
});
