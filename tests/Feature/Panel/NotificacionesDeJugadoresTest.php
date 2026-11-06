<?php

use App\Enums\Rol;
use App\Filament\Resources\Jugadores\Pages\ListJugadores;
use App\Models\Jugador;
use App\Models\Organizador;
use App\Models\SuscripcionPush;
use App\Models\User;
use Database\Seeders\PlanesSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

/** En Jugadores se ve quién tiene activadas las notificaciones en el móvil. */
function dispositivoConPush(?Jugador $jugador, string $dispositivo): SuscripcionPush
{
    $endpoint = 'https://push.example.com/'.uniqid();

    return SuscripcionPush::query()->create([
        'endpoint' => $endpoint,
        'endpoint_hash' => hash('sha256', $endpoint),
        'clave_p256dh' => 'p256dh',
        'clave_auth' => 'auth',
        'jugador_id' => $jugador?->id,
        'dispositivo' => $dispositivo,
    ]);
}

beforeEach(function () {
    $this->seed(PlanesSeeder::class);
    usarOrganizador(Organizador::factory()->create(['id' => 'norte']));
    Filament::setCurrentPanel('panel');
    $this->actingAs(User::factory()->rol(Rol::Propietario)->create());

    $this->ana = Jugador::factory()->create(['nombre' => 'Ana', 'apellidos' => 'Zubiri']);
    $this->bea = Jugador::factory()->create(['nombre' => 'Bea', 'apellidos' => 'Ortiz', 'avisos' => ['correo' => true, 'push' => true]]);
    $this->carla = Jugador::factory()->create(['nombre' => 'Carla', 'apellidos' => 'Pérez', 'avisos' => ['correo' => true, 'push' => false]]);
    $this->dani = Jugador::factory()->create(['nombre' => 'Dani', 'apellidos' => 'Ruiz']);

    dispositivoConPush($this->ana, 'iPhone');
    dispositivoConPush($this->ana, 'Mac');
    dispositivoConPush($this->bea, 'Android');
    dispositivoConPush($this->carla, 'iPhone');
    dispositivoConPush(null, 'Android');
});

test('cada jugador dice si tiene las notificaciones activadas, y en qué dispositivos', function () {
    expect($this->ana->notificacionesEnElMovil())->toBe('activadas')
        ->and($this->bea->notificacionesEnElMovil())->toBe('activadas')
        ->and($this->carla->notificacionesEnElMovil())->toBe('apagadas')
        ->and($this->dani->notificacionesEnElMovil())->toBeNull();

    Livewire::test(ListJugadores::class)
        ->assertSee('2 jugadores tienen activadas las notificaciones en el móvil.')
        ->assertSee('1 dispositivo sigue torneos sin cuenta.')
        ->assertSee('iPhone, Mac')
        ->assertSee('Apagadas por el jugador');
});

test('se filtra por quién las tiene activadas, apagadas o sin activar', function () {
    Livewire::test(ListJugadores::class)
        ->filterTable('notificaciones', 'activadas')
        ->assertCanSeeTableRecords([$this->ana, $this->bea])
        ->assertCanNotSeeTableRecords([$this->carla, $this->dani])
        ->filterTable('notificaciones', 'apagadas')
        ->assertCanSeeTableRecords([$this->carla])
        ->assertCanNotSeeTableRecords([$this->ana, $this->bea, $this->dani])
        ->filterTable('notificaciones', 'no')
        ->assertCanSeeTableRecords([$this->dani])
        ->assertCanNotSeeTableRecords([$this->ana, $this->bea, $this->carla]);
});
