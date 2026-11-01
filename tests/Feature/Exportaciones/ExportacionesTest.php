<?php

use App\Actions\Competicion\GenerarFranjas;
use App\Actions\Competicion\GenerarPartidos;
use App\Actions\Competicion\PlanificarHorarios;
use App\Actions\Competicion\RegistrarResultado;
use App\Actions\Competicion\SortearGrupos;
use App\Competicion\Datos\Resultado;
use App\Competicion\Datos\Set;
use App\Enums\Rol;
use App\Exportaciones\CsvDeInscritos;
use App\Exportaciones\PdfDeCategoria;
use App\Exportaciones\PdfDeHorarios;
use App\Filament\Resources\Categorias\Pages\EditCategoria;
use App\Filament\Resources\Torneos\Pages\EditTorneo;
use App\Models\Categoria;
use App\Models\Fase;
use App\Models\FormatoPartido;
use App\Models\Inscripcion;
use App\Models\Jugador;
use App\Models\Organizador;
use App\Models\Participante;
use App\Models\Pista;
use App\Models\Plan;
use App\Models\Torneo;
use App\Models\User;
use Database\Seeders\PlanesSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PlanesSeeder::class);
    $this->organizador = Organizador::factory()->enPlan(Plan::porClave(Plan::CLUB))->create();
    usarOrganizador($this->organizador);
    Filament::setCurrentPanel('panel');
    $this->actingAs(User::factory()->rol(Rol::Propietario)->create());
    FormatoPartido::factory()->predeterminado()->create(['duracion_estimada' => 60]);

    $this->torneo = Torneo::factory()->enSorteo()->create(['nombre' => 'Open Demo']);
    foreach (Pista::factory()->count(2)->create() as $pista) {
        $this->torneo->torneoPistas()->create(['pista_id' => $pista->id, 'orden' => $pista->orden, 'disponibilidad' => [
            ['fecha' => $this->torneo->fecha_inicio->toDateString(), 'desde' => '09:00', 'hasta' => '21:00'],
        ]]);
    }
    $this->categoria = Categoria::factory()->create(['torneo_id' => $this->torneo->id, 'nombre' => 'Mixta B']);
    $garcia = Jugador::factory()->create(['nombre' => 'Ana', 'apellidos' => 'García', 'email' => 'ana@example.com']);
    foreach (range(1, 4) as $i) {
        $pareja = $i === 1
            ? Participante::factory()->conJugadores($garcia, Jugador::factory()->create(['apellidos' => 'López']))->create(['categoria_id' => $this->categoria->id])
            : Participante::factory()->create(['categoria_id' => $this->categoria->id]);
        Inscripcion::factory()->create(['participante_id' => $pareja->id, 'pago_marcado_en' => $i === 1 ? now() : null]);
    }
    $this->grupos = Fase::factory()->grupos(['tamano_de_grupo' => 4, 'semilla' => 3])->create(['categoria_id' => $this->categoria->id]);
});

test('el PDF de la categoría y el de horarios se generan, y el CSV lleva a las parejas con sus jugadores', function () {
    $usuario = User::query()->firstOrFail();
    app(SortearGrupos::class)->handle($this->grupos, $usuario);
    app(GenerarPartidos::class)->handle($this->grupos, $usuario);
    app(GenerarFranjas::class)->handle($this->torneo);
    app(PlanificarHorarios::class)->handle($this->torneo, $usuario);
    $partido = $this->grupos->partidos()->orderBy('id')->firstOrFail();
    app(RegistrarResultado::class)->handle($partido, new Resultado([new Set(6, 3), new Set(6, 4)]), $usuario);

    $pdf = app(PdfDeCategoria::class)->generar($this->categoria);
    expect(str_starts_with($pdf, '%PDF'))->toBeTrue()
        ->and(app(PdfDeCategoria::class)->nombreDeArchivo($this->categoria))->toBe('open-demo-mixta-b.pdf');

    $horarios = app(PdfDeHorarios::class)->generar($this->torneo);
    expect(str_starts_with($horarios, '%PDF'))->toBeTrue();

    $csv = app(CsvDeInscritos::class)->generar($this->torneo);
    $lineas = preg_split('/\r\n/', trim($csv));
    expect($lineas)->toHaveCount(5)
        ->and($lineas[0])->toContain('Categoría;Pareja;Jugador 1')
        ->and($csv)->toContain('García / López')
        ->and($csv)->toContain('ana@example.com')
        ->and($csv)->toContain(';Sí;');
});

test('los botones de exportar están en el panel y se apagan sin plan', function () {
    Livewire::test(EditCategoria::class, ['record' => $this->categoria->getRouteKey()])
        ->assertActionEnabled('pdf_categoria');
    Livewire::test(EditTorneo::class, ['record' => $this->torneo->getRouteKey()])
        ->assertActionEnabled('pdf_horarios')
        ->assertActionEnabled('csv_inscritos');

    $this->organizador->update(['plan_id' => Plan::porClave(Plan::GRATIS)->id]);

    Livewire::test(EditTorneo::class, ['record' => $this->torneo->getRouteKey()])
        ->assertActionDisabled('csv_inscritos');
});
