<?php

use App\Enums\EstadoInscripcion;
use App\Enums\Rol;
use App\Filament\Resources\Parejas\Pages\EditPareja;
use App\Filament\Resources\Parejas\ParejaResource;
use App\Models\Categoria;
use App\Models\Inscripcion;
use App\Models\Jugador;
use App\Models\Organizador;
use App\Models\Participante;
use App\Models\RegistroDeCambio;
use App\Models\Torneo;
use App\Models\User;
use Database\Seeders\PlanesSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

/** La ficha de una pareja en el panel. */
beforeEach(function () {
    $this->seed(PlanesSeeder::class);
    usarOrganizador(Organizador::factory()->create());
    Filament::setCurrentPanel('panel');
    $this->propietario = User::factory()->rol(Rol::Propietario)->create();
    $this->actingAs($this->propietario);

    $this->categoria = Categoria::factory()->create(['torneo_id' => Torneo::factory()->create(['nombre' => 'Rally 2026'])->id, 'nombre' => 'Iniciación', 'cupo' => 8]);
    $this->ana = Jugador::factory()->create(['nombre' => 'Ana', 'apellidos' => 'García', 'email' => 'ana@example.com', 'nivel_declarado' => '3']);
    $this->eva = Jugador::factory()->create(['nombre' => 'Eva', 'apellidos' => 'López', 'email' => 'eva@example.com', 'nivel_declarado' => '2']);
    $this->inscripcion = Inscripcion::factory()->pendiente()->create([
        'participante_id' => Participante::factory()->conJugadores($this->ana, $this->eva)->create(['categoria_id' => $this->categoria->id])->id,
    ]);
});

test('se cambian el nombre, el estado, el pago, las notas y los datos de los jugadores', function () {
    Livewire::test(EditPareja::class, ['record' => $this->inscripcion->getRouteKey()])
        ->assertOk()
        ->assertSee('Rally 2026 · Iniciación')
        ->assertFormSet(['nombre' => $this->inscripcion->participante->nombre, 'estado' => 'pendiente', "jugadores.{$this->ana->id}.nivel" => '3'])
        ->fillForm([
            'nombre' => 'Las Rápidas',
            'estado' => 'confirmada',
            'pagado' => true,
            'pago_notas' => 'Bizum',
            'notas' => 'Juegan solo por la tarde',
            "jugadores.{$this->ana->id}.apellidos" => 'García Ruiz',
            "jugadores.{$this->ana->id}.telefono" => '600111222',
            "jugadores.{$this->eva->id}.nivel" => '2,5',
            "jugadores.{$this->eva->id}.email" => 'Eva.Lopez@Example.com',
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('Pareja guardada');

    $inscripcion = $this->inscripcion->refresh();
    expect($inscripcion->participante->nombre)->toBe('Las Rápidas')
        ->and($inscripcion->estado)->toBe(EstadoInscripcion::Confirmada)
        ->and($inscripcion->pago_marcado_en)->not->toBeNull()
        ->and($inscripcion->pago_notas)->toBe('Bizum')
        ->and($inscripcion->notas)->toBe('Juegan solo por la tarde')
        ->and($this->ana->refresh()->apellidos)->toBe('García Ruiz')
        ->and($this->ana->getAttribute('telefono'))->toBe('600111222')
        ->and($this->eva->refresh()->nivel_declarado)->toBe('2,5')
        ->and($this->eva->getAttribute('email'))->toBe('eva.lopez@example.com')
        ->and(RegistroDeCambio::query()->where('accion', 'editar_pareja')->exists())->toBeTrue();
});

test('el correo de otro jugador y un nivel que no es un número no se guardan', function () {
    Jugador::factory()->create(['email' => 'cogido@example.com']);

    Livewire::test(EditPareja::class, ['record' => $this->inscripcion->getRouteKey()])
        ->fillForm(["jugadores.{$this->ana->id}.email" => 'cogido@example.com', "jugadores.{$this->eva->id}.nivel" => 'muy bueno'])
        ->call('save')
        ->assertHasFormErrors(["jugadores.{$this->ana->id}.email" => 'unique', "jugadores.{$this->eva->id}.nivel" => 'regex']);

    expect($this->ana->refresh()->getAttribute('email'))->toBe('ana@example.com');
});

test('las listas de parejas llevan a su ficha', function () {
    expect(ParejaResource::getUrl('edit', ['record' => $this->inscripcion]))->toContain("/parejas/{$this->inscripcion->id}/editar");
    Livewire::test(EditPareja::class, ['record' => $this->inscripcion->getRouteKey()])->assertSee('Jugador 1 (capitán)')->assertSee('Jugador 2');
});
