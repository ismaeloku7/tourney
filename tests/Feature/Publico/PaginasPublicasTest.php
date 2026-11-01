<?php

use App\Actions\Competicion\GenerarFranjas;
use App\Actions\Competicion\GenerarPartidos;
use App\Actions\Competicion\PlanificarHorarios;
use App\Actions\Competicion\RegistrarResultado;
use App\Actions\Competicion\SortearGrupos;
use App\Competicion\Datos\Resultado;
use App\Competicion\Datos\Set;
use App\Enums\EstadoTorneo;
use App\Enums\Rol;
use App\Livewire\Publico\FichaDelTorneo;
use App\Models\Categoria;
use App\Models\Fase;
use App\Models\FormatoPartido;
use App\Models\Inscripcion;
use App\Models\Jugador;
use App\Models\Organizador;
use App\Models\Participante;
use App\Models\Pista;
use App\Models\Torneo;
use App\Models\User;
use Database\Seeders\PlanesSeeder;
use Livewire\Livewire;

/**
 * Un torneo publicado de una categoría con 4 parejas, sorteado, con
 * horarios y un resultado, en el organizador «norte».
 */
beforeEach(function () {
    $this->seed(PlanesSeeder::class);
    $this->organizador = Organizador::factory()->create(['id' => 'norte', 'nombre' => 'Pádel Norte']);

    comoOrganizador($this->organizador, function (): void {
        $usuario = User::factory()->rol(Rol::Propietario)->create();
        FormatoPartido::factory()->predeterminado()->create(['duracion_estimada' => 60]);

        $this->torneo = Torneo::factory()->enEstado(EstadoTorneo::EnJuego)->create(['nombre' => 'Open de Otoño', 'slug' => 'open-otono', 'normativa' => 'Bolas nuevas en cada partido.']);
        $this->pista = Pista::factory()->create(['nombre' => 'Central']);
        $this->torneo->torneoPistas()->create(['pista_id' => $this->pista->id, 'orden' => 1, 'disponibilidad' => [
            ['fecha' => $this->torneo->fecha_inicio->toDateString(), 'desde' => '09:00', 'hasta' => '21:00'],
        ]]);

        $categoria = Categoria::factory()->create(['torneo_id' => $this->torneo->id, 'nombre' => 'Mixta B', 'cupo' => 8]);
        $garcia = Jugador::factory()->create(['nombre' => 'Ana', 'apellidos' => 'García', 'alias' => 'La Zurda', 'email' => 'ana@example.com', 'telefono' => '600111222']);
        $lopez = Jugador::factory()->create(['nombre' => 'Eva', 'apellidos' => 'López', 'alias' => 'Eva L.']);
        foreach (range(1, 4) as $i) {
            $pareja = $i === 1
                ? Participante::factory()->conJugadores($garcia, $lopez)->create(['categoria_id' => $categoria->id, 'nombre' => 'García / López'])
                : Participante::factory()->create(['categoria_id' => $categoria->id]);
            Inscripcion::factory()->create(['participante_id' => $pareja->id]);
        }

        $grupos = Fase::factory()->grupos(['tamano_de_grupo' => 4, 'semilla' => 3])->create(['categoria_id' => $categoria->id, 'nombre' => 'Fase de grupos']);
        app(SortearGrupos::class)->handle($grupos, $usuario);
        app(GenerarPartidos::class)->handle($grupos, $usuario);
        app(GenerarFranjas::class)->handle($this->torneo);
        app(PlanificarHorarios::class)->handle($this->torneo, $usuario);
        $this->torneo->update(['horarios_publicados_en' => now()]);

        $partido = $grupos->partidos()->orderBy('id')->firstOrFail();
        app(RegistrarResultado::class)->handle($partido, new Resultado([new Set(6, 3), new Set(6, 4)]), $usuario);

        Torneo::factory()->create(['nombre' => 'Torneo secreto', 'slug' => 'secreto']);
    });
});

test('la portada del organizador lista sus torneos publicados y no los borradores', function () {
    $this->get('http://norte.saas.test/')
        ->assertOk()
        ->assertSee('Pádel Norte')
        ->assertSee('En juego')
        ->assertSee('Open de Otoño')
        ->assertDontSee('Torneo secreto');
});

test('la ficha enseña datos, categorías, inscritos, grupos y horarios sin correos ni teléfonos', function () {
    $this->get('http://norte.saas.test/torneos/open-otono')
        ->assertOk()
        ->assertSee('Open de Otoño')
        ->assertSee('Bolas nuevas en cada partido.')
        ->assertSee('Mixta B')
        ->assertSee('4 plazas libres');

    usarOrganizador($this->organizador);
    $torneo = Torneo::query()->where('slug', 'open-otono')->sole();

    $ficha = Livewire::test(FichaDelTorneo::class, ['torneo' => $torneo]);

    $ficha->call('ver', 'inscritos')->assertSee('García / López')->assertDontSee('ana@example.com')->assertDontSee('600111222');
    $ficha->call('ver', 'grupos')->assertSee('Grupo A')->assertSee('6-3 6-4');
    $ficha->call('ver', 'horarios')->assertSee('Central');
    $ficha->call('ver', 'cuadro')->assertSee('El cuadro se llena');

    // Con privacidad, los alias en lugar de los nombres.
    $torneo->update(['privacidad_alias' => true]);
    Livewire::test(FichaDelTorneo::class, ['torneo' => $torneo])
        ->call('ver', 'inscritos')
        ->assertSee('La Zurda / Eva L.')
        ->assertDontSee('García / López');
});

test('un borrador, un torneo de otro organizador o el dominio de la plataforma no tienen ficha', function () {
    $this->get('http://norte.saas.test/torneos/secreto')->assertNotFound();
    $this->get('http://norte.saas.test/torneos/no-existe')->assertNotFound();
    $this->get('http://saas.test/torneos/open-otono')->assertNotFound();

    Organizador::factory()->create(['id' => 'sur']);
    $this->get('http://sur.saas.test/torneos/open-otono')->assertNotFound();
});

test('el tablero y la página de la pista responden con sus partidos', function () {
    $this->get('http://norte.saas.test/torneos/open-otono/tablero')
        ->assertOk()
        ->assertSee('Open de Otoño')
        ->assertSee('Últimos resultados')
        ->assertSee('6-3 6-4');

    $this->get("http://norte.saas.test/torneos/open-otono/pista/{$this->pista->id}")
        ->assertOk()
        ->assertSee('Central')
        ->assertSee('Mixta B')
        ->assertSee('Código QR de esta página', false);

    $otra = comoOrganizador($this->organizador, fn () => Pista::factory()->create());
    $this->get("http://norte.saas.test/torneos/open-otono/pista/{$otra->id}")->assertNotFound();
});
