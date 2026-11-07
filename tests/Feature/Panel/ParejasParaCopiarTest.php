<?php

use App\Enums\Rol;
use App\Filament\Resources\Jugadores\JugadorResource;
use App\Filament\Resources\Parejas\Pages\ListParejas;
use App\Filament\Resources\Parejas\ParaCopiar;
use App\Filament\Resources\Parejas\ParejaResource;
use App\Filament\Resources\Parejas\TextoParaWhatsApp;
use App\Filament\Resources\Torneos\Pages\EditTorneo;
use App\Filament\Resources\Torneos\RelationManagers\ParejasRelationManager;
use App\Models\Categoria;
use App\Models\Inscripcion;
use App\Models\Jugador;
use App\Models\Organizador;
use App\Models\Participante;
use App\Models\Torneo;
use App\Models\User;
use Database\Seeders\PlanesSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\Column;
use Livewire\Livewire;

/** Los listados de parejas se dejan limpios para copiarlos y pegarlos en WhatsApp. */
beforeEach(function () {
    $this->seed(PlanesSeeder::class);
    usarOrganizador(Organizador::factory()->create());
    Filament::setCurrentPanel('panel');

    $this->torneo = Torneo::factory()->create();
    $categoria = Categoria::factory()->create(['torneo_id' => $this->torneo->id]);
    $this->ana = Jugador::factory()->create(['nombre' => 'Ana']);
    $this->inscripcion = Inscripcion::factory()->create([
        'participante_id' => Participante::factory()->conJugadores($this->ana, Jugador::factory()->create())->create(['categoria_id' => $categoria->id])->id,
    ]);
    $this->actingAs(User::factory()->rol(Rol::Propietario)->create());
});

test('en Parejas todas las columnas se pueden ocultar', function () {
    $columnas = Livewire::test(ListParejas::class)->instance()->getTable()->getColumns();

    expect($columnas)->not->toBeEmpty()
        ->and(array_filter($columnas, fn (Column $c): bool => ! $c->isToggleable()))->toBe([]);
});

test('la vista para copiar quita las acciones, las casillas y los enlaces, y se queda puesta', function () {
    $ficha = ParejaResource::getUrl('edit', ['record' => $this->inscripcion]);
    $jugador = JugadorResource::getUrl('edit', ['record' => $this->ana]);

    Livewire::test(ListParejas::class)
        ->assertActionVisible(TestAction::make('editar')->table($this->inscripcion))
        ->assertActionVisible(TestAction::make('moverMarcadas')->table()->bulk())
        ->assertSeeHtml($ficha)
        ->assertSeeHtml($jugador)
        ->callAction(TestAction::make('paraCopiar')->table())
        ->assertActionHidden(TestAction::make('editar')->table($this->inscripcion))
        ->assertActionHidden(TestAction::make('mover')->table($this->inscripcion))
        ->assertActionHidden(TestAction::make('crearAcceso')->table($this->inscripcion))
        ->assertActionHidden(TestAction::make('moverMarcadas')->table()->bulk())
        ->assertDontSeeHtml($ficha)
        ->assertDontSeeHtml($jugador)
        ->assertSee('Ana');

    // Al volver a entrar sigue igual, hasta que se vuelve a la vista normal.
    Livewire::test(ListParejas::class)
        ->assertActionHidden(TestAction::make('editar')->table($this->inscripcion))
        ->callAction(TestAction::make('paraCopiar')->table())
        ->assertActionVisible(TestAction::make('editar')->table($this->inscripcion))
        ->assertSeeHtml($ficha);
});

test('la pestaña Parejas del torneo también tiene la vista para copiar', function () {
    $columnas = Livewire::test(ParejasRelationManager::class, ['ownerRecord' => $this->torneo, 'pageClass' => EditTorneo::class])
        ->assertActionVisible(TestAction::make('editar')->table($this->inscripcion))
        ->callAction(TestAction::make('paraCopiar')->table())
        ->assertActionHidden(TestAction::make('editar')->table($this->inscripcion))
        ->assertActionHidden(TestAction::make('moverMarcadas')->table()->bulk())
        ->instance()->getTable()->getColumns();

    expect(array_filter($columnas, fn (Column $c): bool => ! $c->isToggleable()))->toBe([]);
});

test('«Copiar para WhatsApp» da el texto limpio de lo que sale en la tabla', function () {
    $this->inscripcion->load('categoria.torneo', 'participante.jugadores');
    $pareja = $this->inscripcion->participante;
    $jugadores = $pareja->jugadores->map(fn (Jugador $j): string => $j->nombre_completo)->join(' y ');
    $categoria = $this->inscripcion->categoria;

    $componente = Livewire::test(ListParejas::class)
        ->mountAction(TestAction::make('copiarParaWhatsApp')->table())
        ->assertActionMounted(TestAction::make('copiarParaWhatsApp')->table());

    $texto = ParaCopiar::texto($componente->instance());

    expect($texto)->toStartWith("*{$this->torneo->nombre} · {$categoria->nombre}*\n1. {$pareja->nombre}: {$jugadores} · Nivel ")
        ->and($texto)->toContain($this->inscripcion->estado->etiqueta())
        ->and($texto)->not->toContain('Editar')
        ->and($texto)->not->toContain('Mover');

    // Solo con lo que se ve; sin categoría, sin cabecera.
    expect(TextoParaWhatsApp::componer([$this->inscripcion], ['participante.nombre', 'jugadores']))->toBe("1. {$pareja->nombre}: {$jugadores}")
        ->and(TextoParaWhatsApp::componer([$this->inscripcion], ['pago_marcado_en']))->toBe('1. Sin pagar')
        ->and(TextoParaWhatsApp::componer([], ['participante.nombre']))->toBe('');
});
