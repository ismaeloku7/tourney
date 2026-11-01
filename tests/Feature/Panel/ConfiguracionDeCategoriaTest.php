<?php

use App\Enums\EstadoFase;
use App\Enums\Rol;
use App\Enums\TipoDeFase;
use App\Filament\Resources\Categorias\Pages\EditCategoria;
use App\Filament\Resources\Categorias\RelationManagers\FasesRelationManager;
use App\Filament\Resources\Pistas\Pages\ListPistas;
use App\Filament\Resources\Torneos\Pages\EditTorneo;
use App\Filament\Resources\Torneos\RelationManagers\PistasRelationManager;
use App\Models\Categoria;
use App\Models\Fase;
use App\Models\Organizador;
use App\Models\Pista;
use App\Models\Sede;
use App\Models\Torneo;
use App\Models\TorneoPista;
use App\Models\User;
use Database\Seeders\PlanesSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PlanesSeeder::class);
    usarOrganizador(Organizador::factory()->create());
    Filament::setCurrentPanel('panel');

    $this->propietario = User::factory()->rol(Rol::Propietario)->create();
    $this->administrador = User::factory()->rol(Rol::Administrador)->create();
    $this->torneo = Torneo::factory()->create(['fecha_inicio' => '2027-05-07', 'fecha_fin' => '2027-05-09']);
    $this->categoria = Categoria::factory()->create(['torneo_id' => $this->torneo->id, 'nombre' => 'Mixta B']);
});

test('la ficha de la categoría se abre desde el torneo y un administrador solo llega a las de sus torneos', function () {
    $this->actingAs($this->propietario);
    Livewire::test(EditCategoria::class, ['record' => $this->categoria->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('Mixta B')
        ->assertSee($this->torneo->nombre);

    $this->actingAs($this->administrador);
    Livewire::test(EditCategoria::class, ['record' => $this->categoria->getRouteKey()])->assertNotFound();

    $this->torneo->administradores()->attach($this->administrador);
    Livewire::test(EditCategoria::class, ['record' => $this->categoria->getRouteKey()])->assertSuccessful();
});

test('una fase de grupos se guarda con la configuración que deja el motor', function () {
    $this->actingAs($this->propietario);

    Livewire::test(FasesRelationManager::class, ['ownerRecord' => $this->categoria, 'pageClass' => EditCategoria::class])
        ->callAction(TestAction::make('create')->table(), [
            'tipo' => TipoDeFase::Grupos->value,
            'nombre' => 'Fase de grupos',
            'orden' => 1,
            'reparto' => 'tamano',
            'configuracion' => [
                'tamano_de_grupo' => 4,
                'numero_de_grupos' => 3,
                'modo_de_sorteo' => 'cabezas_de_serie',
                'ida_y_vuelta' => false,
                'puntuacion' => ['victoria' => 3, 'derrota' => 0, 'por_set_ganado' => null],
                'criterios_de_desempate' => ['puntos', 'enfrentamiento_directo', 'diferencia_de_sets', 'sorteo'],
                'clasificados' => ['por_grupo' => 2, 'cuantos_mejores' => 2, 'mejores_de_posicion' => 3],
            ],
        ])
        ->assertHasNoActionErrors()
        ->assertNotNotified('La configuración de la fase no es válida');

    $fase = Fase::query()->where('categoria_id', $this->categoria->id)->sole();
    $configuracion = $fase->configuracionDeGrupos();

    expect($fase->tipo)->toBe(TipoDeFase::Grupos)
        ->and($fase->estado)->toBe(EstadoFase::Pendiente)
        ->and($configuracion->tamanoDeGrupo)->toBe(4)
        ->and($configuracion->numeroDeGrupos)->toBeNull() // el reparto era por tamaño
        ->and($configuracion->puntuacion->victoria)->toBe(3)
        ->and($configuracion->clasificados->cuantosMejores)->toBe(2)
        ->and($configuracion->clasificados->mejoresDePosicion)->toBe(3)
        ->and(array_map(fn ($c) => $c->value, $configuracion->criteriosDeDesempate))->toBe(['puntos', 'enfrentamiento_directo', 'diferencia_de_sets', 'sorteo']);
});

test('una configuración que el motor rechaza no se guarda y se avisa con sus frases', function () {
    $this->actingAs($this->propietario);

    Livewire::test(FasesRelationManager::class, ['ownerRecord' => $this->categoria, 'pageClass' => EditCategoria::class])
        ->callAction(TestAction::make('create')->table(), [
            'tipo' => TipoDeFase::Grupos->value,
            'nombre' => 'Grupos',
            'orden' => 1,
            'reparto' => 'tamano',
            'configuracion' => [
                'tamano_de_grupo' => 4,
                'modo_de_sorteo' => 'aleatorio',
                'puntuacion' => ['victoria' => 2, 'derrota' => 0],
                'criterios_de_desempate' => ['puntos'],
                'clasificados' => ['por_grupo' => 2, 'cuantos_mejores' => 2], // falta de qué posición
            ],
        ])
        ->assertNotified('La configuración de la fase no es válida');

    expect(Fase::query()->count())->toBe(0);
});

test('un cuadro y una liga se crean con sus valores por defecto; una fase sorteada no se edita', function () {
    $this->actingAs($this->propietario);
    $componente = Livewire::test(FasesRelationManager::class, ['ownerRecord' => $this->categoria, 'pageClass' => EditCategoria::class]);

    $componente->callAction(TestAction::make('create')->table(), [
        'tipo' => TipoDeFase::Eliminatoria->value, 'nombre' => 'Cuadro final', 'orden' => 2,
        'configuracion' => ['tamano_de_cuadro' => 8, 'regla_de_cruces' => 'entre_grupos', 'evitar_reencuentros' => true, 'tercer_y_cuarto_puesto' => true, 'consolacion' => 'ninguna'],
    ])->assertHasNoActionErrors();

    $componente->callAction(TestAction::make('create')->table(), [
        'tipo' => TipoDeFase::Liga->value, 'nombre' => 'Liguilla', 'orden' => 3,
        'configuracion' => ['ida_y_vuelta' => true, 'puntuacion' => ['victoria' => 2, 'derrota' => 0], 'criterios_de_desempate' => ['puntos', 'sorteo']],
    ])->assertHasNoActionErrors();

    $cuadro = Fase::query()->where('nombre', 'Cuadro final')->sole();
    $liga = Fase::query()->where('nombre', 'Liguilla')->sole();

    expect($cuadro->configuracionDeEliminatoria()->tamanoDeCuadro)->toBe(8)
        ->and($cuadro->configuracionDeEliminatoria()->tercerYCuartoPuesto)->toBeTrue()
        ->and($liga->configuracionDeGrupos()->numeroDeGrupos)->toBe(1)
        ->and($liga->configuracionDeGrupos()->idaYVuelta)->toBeTrue();

    $cuadro->update(['estado' => EstadoFase::Preparada]);

    $componente->assertActionVisible(TestAction::make('edit')->table($liga))
        ->assertActionHidden(TestAction::make('edit')->table($cuadro))
        ->assertActionHidden(TestAction::make('delete')->table($cuadro));
});

test('las pistas del torneo se añaden con su disponibilidad validada', function () {
    $sede = Sede::factory()->create();
    $this->torneo->update(['sede_id' => $sede->id]);
    $central = Pista::factory()->create(['sede_id' => $sede->id, 'nombre' => 'Central']);
    Pista::factory()->create(['sede_id' => $sede->id, 'nombre' => 'Pista 2']);
    Pista::factory()->create(['sede_id' => $sede->id, 'nombre' => 'Cerrada', 'activa' => false]);
    $this->actingAs($this->propietario);

    $componente = Livewire::test(PistasRelationManager::class, ['ownerRecord' => $this->torneo, 'pageClass' => EditTorneo::class]);

    $componente->callAction(TestAction::make('create')->table(), [
        'pista_id' => $central->id,
        'orden' => 1,
        'disponibilidad' => [
            ['fecha' => '2027-05-07', 'desde' => '16:00', 'hasta' => '22:00'],
            ['fecha' => '2027-05-08', 'desde' => '09:00', 'hasta' => '14:00'],
        ],
    ])->assertHasNoActionErrors();

    $torneoPista = TorneoPista::query()->where('pista_id', $central->id)->sole();
    expect($torneoPista->disponibilidad)->toBe([
        ['fecha' => '2027-05-07', 'desde' => '16:00', 'hasta' => '22:00'],
        ['fecha' => '2027-05-08', 'desde' => '09:00', 'hasta' => '14:00'],
    ])->and(PistasRelationManager::resumen($torneoPista))->toBe('2 días · 11 h');

    // Un tramo del revés no pasa.
    $componente->callAction(TestAction::make('edit')->table($torneoPista), [
        'pista_id' => $central->id,
        'orden' => 1,
        'disponibilidad' => [['fecha' => '2027-05-07', 'desde' => '20:00', 'hasta' => '10:00']],
    ])->assertNotified('La disponibilidad no es válida');
    expect($torneoPista->refresh()->disponibilidad)->toHaveCount(2);

    // «Añadir varias» trae, de una vez, las activas de la sede que faltan con la disponibilidad que se ponga.
    // (Componente nuevo: el formulario rechazado se queda abierto en el anterior.)
    $otraSede = Sede::factory()->create();
    Pista::factory()->create(['sede_id' => $otraSede->id, 'nombre' => 'Lejana']);
    Livewire::test(PistasRelationManager::class, ['ownerRecord' => $this->torneo, 'pageClass' => EditTorneo::class])
        ->callAction(TestAction::make('anadirVarias')->table())
        ->assertHasNoActionErrors()
        ->assertNotified('Una pista añadida');

    expect($this->torneo->torneoPistas()->count())->toBe(2)
        ->and(TorneoPista::query()->whereHas('pista', fn ($q) => $q->where('nombre', 'Pista 2'))->sole()->disponibilidad)->toHaveCount(3)
        ->and(PistasRelationManager::disponibilidadPorDefecto($this->torneo)[0])->toBe(['fecha' => '2027-05-07', 'desde' => '09:00', 'hasta' => '22:00']);
});

test('varias pistas se añaden y se ajustan en bloque con la misma disponibilidad', function () {
    $sede = Sede::factory()->create();
    $pistas = collect(range(1, 4))->map(fn (int $n): Pista => Pista::factory()->create(['sede_id' => $sede->id, 'nombre' => "Pista {$n}", 'orden' => $n]));
    $this->actingAs($this->propietario);
    $mañanas = [
        ['fecha' => '2027-05-08', 'desde' => '09:00', 'hasta' => '14:00'],
        ['fecha' => '2027-05-09', 'desde' => '09:00', 'hasta' => '14:00'],
    ];

    Livewire::test(PistasRelationManager::class, ['ownerRecord' => $this->torneo, 'pageClass' => EditTorneo::class])
        ->callAction(TestAction::make('anadirVarias')->table(), [
            'sede_id' => $sede->id,
            'pistas' => $pistas->take(3)->pluck('id')->all(),
            'disponibilidad' => $mañanas,
        ])
        ->assertHasNoActionErrors()
        ->assertNotified('3 pistas añadidas');

    $enElTorneo = TorneoPista::query()->orderBy('orden')->get();
    expect($enElTorneo->pluck('pista_id')->all())->toBe($pistas->take(3)->pluck('id')->all())
        ->and($enElTorneo->pluck('orden')->all())->toBe([1, 2, 3])
        ->and($enElTorneo->every(fn (TorneoPista $tp): bool => $tp->disponibilidad === $mañanas))->toBeTrue();

    $tardes = [['fecha' => '2027-05-07', 'desde' => '16:00', 'hasta' => '22:00']];
    Livewire::test(PistasRelationManager::class, ['ownerRecord' => $this->torneo, 'pageClass' => EditTorneo::class])
        ->selectTableRecords($enElTorneo->take(2)->modelKeys())
        ->callAction(TestAction::make('disponibilidad')->table()->bulk(), ['disponibilidad' => $tardes])
        ->assertHasNoActionErrors()
        ->assertNotified('Disponibilidad guardada en 2 pistas');

    expect($enElTorneo->map(fn (TorneoPista $tp): array => $tp->refresh()->disponibilidad)->all())->toBe([$tardes, $tardes, $mañanas]);
});

test('en instalaciones se crean varias pistas numeradas de una vez', function () {
    $sede = Sede::factory()->create();
    Pista::factory()->create(['sede_id' => $sede->id, 'nombre' => 'Central', 'orden' => 1]);
    $this->actingAs($this->propietario);

    Livewire::test(ListPistas::class)
        ->callAction('crearVarias', ['sede_id' => $sede->id, 'prefijo' => 'Pista', 'desde' => 2, 'cuantas' => 3])
        ->assertHasNoActionErrors()
        ->assertNotified('3 pistas creadas');

    expect(Pista::query()->where('sede_id', $sede->id)->orderBy('orden')->pluck('nombre')->all())->toBe(['Central', 'Pista 2', 'Pista 3', 'Pista 4']);
});
