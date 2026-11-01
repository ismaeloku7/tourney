<?php

use App\Actions\Competicion\AnularResultado;
use App\Actions\Competicion\CambiarEstadoDelTorneo;
use App\Actions\Competicion\CerrarFase;
use App\Actions\Competicion\GenerarFranjas;
use App\Actions\Competicion\GenerarPartidos;
use App\Actions\Competicion\PlanificarHorarios;
use App\Actions\Competicion\RegistrarResultado;
use App\Actions\Competicion\SortearGrupos;
use App\Actions\Competicion\ValidarResultado;
use App\Competicion\Datos\Resultado;
use App\Competicion\Datos\Set;
use App\Competicion\Errores\AvanceNoReversible;
use App\Competicion\Errores\FaseConResultados;
use App\Competicion\Errores\OperacionNoPermitida;
use App\Competicion\Errores\ResultadoInvalido;
use App\Enums\EstadoFase;
use App\Enums\EstadoPartido;
use App\Enums\EstadoTorneo;
use App\Enums\ResultadosPorJugadores;
use App\Enums\Rol;
use App\Models\Categoria;
use App\Models\Clasificacion;
use App\Models\Fase;
use App\Models\FormatoPartido;
use App\Models\Inscripcion;
use App\Models\Organizador;
use App\Models\Participante;
use App\Models\Partido;
use App\Models\Pista;
use App\Models\RegistroDeCambio;
use App\Models\Torneo;
use App\Models\User;
use Database\Seeders\PlanesSeeder;

/**
 * Un torneo de una categoría con 12 parejas confirmadas, 4 pistas de 9 a
 * 21, fase de grupos de 4 (2 por grupo + 2 mejores terceros), cuadro con
 * consolación de no clasificados.
 */
beforeEach(function () {
    $this->seed(PlanesSeeder::class);
    usarOrganizador(Organizador::factory()->create(['id' => 'demo']));

    $this->propietario = User::factory()->rol(Rol::Propietario)->create();
    FormatoPartido::factory()->predeterminado()->create(['duracion_estimada' => 60]);

    $this->torneo = Torneo::factory()->enSorteo()->create(['descanso_minimo_minutos' => 30]);
    foreach (Pista::factory()->count(4)->create() as $pista) {
        $this->torneo->torneoPistas()->create(['pista_id' => $pista->id, 'orden' => $pista->orden, 'disponibilidad' => [
            ['fecha' => $this->torneo->fecha_inicio->toDateString(), 'desde' => '09:00', 'hasta' => '21:00'],
            ['fecha' => $this->torneo->fecha_fin->toDateString(), 'desde' => '09:00', 'hasta' => '21:00'],
        ]]);
    }

    $this->categoria = Categoria::factory()->create(['torneo_id' => $this->torneo->id, 'cupo' => 16]);
    for ($i = 1; $i <= 12; $i++) {
        $pareja = Participante::factory()->create(['categoria_id' => $this->categoria->id, 'cabeza_de_serie' => $i <= 3 ? $i : null]);
        Inscripcion::factory()->create(['participante_id' => $pareja->id]);
    }
    // Una pareja en lista de espera y otra de baja no entran en el sorteo.
    Inscripcion::factory()->enEspera()->create(['participante_id' => Participante::factory()->create(['categoria_id' => $this->categoria->id])->id]);
    Inscripcion::factory()->deBaja()->create(['participante_id' => Participante::factory()->create(['categoria_id' => $this->categoria->id])->id]);

    $this->grupos = Fase::factory()->grupos(['tamano_de_grupo' => 4, 'modo_de_sorteo' => 'cabezas_de_serie', 'semilla' => 7, 'clasificados' => ['por_grupo' => 2, 'mejores_de_posicion' => 3, 'cuantos_mejores' => 2]])->create(['categoria_id' => $this->categoria->id]);
    $this->cuadro = Fase::factory()->eliminatoria(['consolacion' => 'no_clasificados', 'tercer_y_cuarto_puesto' => true])->create(['categoria_id' => $this->categoria->id]);
    $this->consolacion = Fase::factory()->consolacion()->create(['categoria_id' => $this->categoria->id]);
});

function resultadoDe(array $sets = [[6, 3], [6, 4]]): Resultado
{
    return new Resultado(array_map(fn (array $s): Set => new Set($s[0], $s[1]), $sets));
}

function jugarTodos(Fase $fase, User $usuario): void
{
    for ($vuelta = 0; $vuelta < 10; $vuelta++) {
        $pendientes = $fase->partidos()->whereNotNull('local_id')->whereNotNull('visitante_id')->whereIn('estado', ['pendiente', 'programado'])->orderBy('id')->get();
        if ($pendientes->isEmpty()) {
            return;
        }
        foreach ($pendientes->values() as $n => $partido) {
            app(RegistrarResultado::class)->handle($partido, resultadoDe($n % 3 === 2 ? [[3, 6], [4, 6]] : [[6, 3], [6, 4]]), $usuario);
        }
    }
}

test('sortear reparte las 12 confirmadas en 3 grupos de 4 y guarda la semilla', function () {
    $reparto = app(SortearGrupos::class)->handle($this->grupos, $this->propietario);

    expect($reparto->grupos)->toHaveCount(3)
        ->and($this->grupos->refresh())->estado->toBe(EstadoFase::Preparada)->semilla_sorteo->toBe(7)
        ->and($this->grupos->grupos()->count())->toBe(3)
        ->and($this->grupos->grupos->sum(fn ($g) => $g->participantes()->count()))->toBe(12)
        ->and($this->grupos->grupos->pluck('nombre')->all())->toBe(['A', 'B', 'C'])
        ->and(RegistroDeCambio::query()->where('accion', 'sortear_grupos')->count())->toBe(1);

    // Las cabezas de serie 1, 2 y 3 van cada una a un grupo.
    $cabezas = Participante::query()->whereNotNull('cabeza_de_serie')->get();
    expect($cabezas->map(fn ($p) => $p->grupos()->first()->nombre)->sort()->values()->all())->toBe(['A', 'B', 'C']);
});

test('generar partidos crea 18 pendientes y las clasificaciones a cero, y planificar los coloca sin conflictos', function () {
    app(SortearGrupos::class)->handle($this->grupos, $this->propietario);

    expect(app(GenerarPartidos::class)->handle($this->grupos, $this->propietario))->toBe(18)
        ->and($this->grupos->partidos()->where('estado', EstadoPartido::Pendiente->value)->count())->toBe(18)
        ->and(Clasificacion::query()->where('fase_id', $this->grupos->id)->count())->toBe(12)
        ->and(Clasificacion::query()->where('fase_id', $this->grupos->id)->sum('puntos'))->toBe(0);

    expect(app(GenerarFranjas::class)->handle($this->torneo))->toBe(4 * 2 * 12);

    $planificacion = app(PlanificarHorarios::class)->handle($this->torneo, $this->propietario);

    expect($planificacion->conflictos)->toBe([])
        ->and($planificacion->asignaciones)->toHaveCount(18)
        ->and($this->grupos->partidos()->where('estado', EstadoPartido::Programado->value)->whereNotNull('franja_id')->count())->toBe(18);

    // Repetir no cambia nada: ya están todos fijados.
    expect(app(PlanificarHorarios::class)->handle($this->torneo, $this->propietario)->asignaciones)->toBe([]);
});

test('registrar un resultado recalcula la tabla del grupo y pone la fase en juego', function () {
    app(SortearGrupos::class)->handle($this->grupos, $this->propietario);
    app(GenerarPartidos::class)->handle($this->grupos, $this->propietario);

    $partido = $this->grupos->partidos()->orderBy('id')->firstOrFail();
    $partido = app(RegistrarResultado::class)->handle($partido, resultadoDe([[6, 3], [6, 4]]), $this->propietario);

    expect($partido->estado)->toBe(EstadoPartido::Jugado)
        ->and($partido->ganador_id)->toBe($partido->local_id)
        ->and($partido->sets)->toHaveCount(2)
        ->and($this->grupos->refresh()->estado)->toBe(EstadoFase::EnJuego);

    $linea = Clasificacion::query()->where('grupo_id', $partido->grupo_id)->where('participante_id', $partido->local_id)->sole();
    expect($linea)->puntos->toBe(2)->pj->toBe(1)->pg->toBe(1)->sets_favor->toBe(2)->juegos_favor->toBe(12)->posicion->toBe(1);

    expect(fn () => app(RegistrarResultado::class)->handle($partido, resultadoDe(), $this->propietario))->toThrow(OperacionNoPermitida::class)
        ->and(fn () => app(SortearGrupos::class)->handle($this->grupos, $this->propietario))->toThrow(FaseConResultados::class);

    // Forzar el sorteo anula lo jugado.
    app(SortearGrupos::class)->handle($this->grupos, $this->propietario, forzar: true);
    expect($this->grupos->partidos()->count())->toBe(0);
});

test('un resultado que no cumple el formato no se guarda', function () {
    app(SortearGrupos::class)->handle($this->grupos, $this->propietario);
    app(GenerarPartidos::class)->handle($this->grupos, $this->propietario);
    $partido = $this->grupos->partidos()->orderBy('id')->firstOrFail();

    expect(fn () => app(RegistrarResultado::class)->handle($partido, resultadoDe([[7, 3], [6, 4]]), $this->propietario))->toThrow(ResultadoInvalido::class)
        ->and($partido->refresh()->estado)->toBe(EstadoPartido::Pendiente)
        ->and($partido->sets()->count())->toBe(0);
});

test('cerrar la fase de grupos exige todos los resultados y rellena el cuadro de 8 con mejores terceros y la consolación', function () {
    app(SortearGrupos::class)->handle($this->grupos, $this->propietario);
    app(GenerarPartidos::class)->handle($this->grupos, $this->propietario);

    expect(fn () => app(CerrarFase::class)->handle($this->grupos, $this->propietario))->toThrow(OperacionNoPermitida::class, 'sin resultado');

    jugarTodos($this->grupos, $this->propietario);
    $clasificados = app(CerrarFase::class)->handle($this->grupos, $this->propietario);

    expect($clasificados)->toHaveCount(8)
        ->and(array_filter($clasificados, fn ($c) => str_starts_with($c->origen, 'mejor 3º')))->toHaveCount(2)
        ->and($this->grupos->refresh()->estado)->toBe(EstadoFase::Cerrada)
        ->and(Clasificacion::query()->where('fase_id', $this->grupos->id)->whereNotNull('clasificado_como')->count())->toBe(8);

    $cuadro = $this->cuadro->refresh();
    expect($cuadro->estado)->toBe(EstadoFase::Preparada)
        ->and($cuadro->fase_origen_id)->toBe($this->grupos->id)
        ->and($cuadro->partidos()->count())->toBe(8)
        ->and($cuadro->partidos()->where('ronda', 1)->whereNotNull('local_id')->whereNotNull('visitante_id')->count())->toBe(4)
        ->and($cuadro->partidos()->where('es_tercer_puesto', true)->count())->toBe(1);

    // Los orígenes de la segunda ronda apuntan a ids reales de partidos de la primera.
    $segunda = $cuadro->partidos()->where('ronda', 2)->orderBy('posicion')->first();
    expect(Partido::query()->whereKey($segunda->origen_local['referencia'])->where('ronda', 1)->exists())->toBeTrue();

    $consolacion = $this->consolacion->refresh();
    expect($consolacion->estado)->toBe(EstadoFase::Preparada)
        ->and($consolacion->partidos()->count())->toBe(3)
        ->and($consolacion->partidos()->where('ronda', 1)->whereNotNull('local_id')->count())->toBe(2);
});

test('anular un resultado que ya había avanzado al siguiente partido lo revierte, y no se puede si el siguiente ya se jugó', function () {
    app(SortearGrupos::class)->handle($this->grupos, $this->propietario);
    app(GenerarPartidos::class)->handle($this->grupos, $this->propietario);
    jugarTodos($this->grupos, $this->propietario);
    app(CerrarFase::class)->handle($this->grupos, $this->propietario);

    $cuartos = $this->cuadro->partidos()->where('ronda', 1)->orderBy('posicion')->get();
    $semifinal = $this->cuadro->partidos()->where('ronda', 2)->where('posicion', 1)->sole();

    $primero = app(RegistrarResultado::class)->handle($cuartos[0], resultadoDe(), $this->propietario);
    expect($semifinal->refresh()->local_id)->toBe($primero->ganador_id);

    app(AnularResultado::class)->handle($primero, $this->propietario);
    expect($semifinal->refresh()->local_id)->toBeNull()
        ->and($primero->refresh()->estado)->toBe(EstadoPartido::Pendiente)
        ->and($primero->ganador_id)->toBeNull()
        ->and($primero->sets()->count())->toBe(0);

    app(RegistrarResultado::class)->handle($primero, resultadoDe(), $this->propietario);
    app(RegistrarResultado::class)->handle($cuartos[1], resultadoDe(), $this->propietario);
    app(RegistrarResultado::class)->handle($semifinal->refresh(), resultadoDe(), $this->propietario);

    expect(fn () => app(AnularResultado::class)->handle($primero->refresh(), $this->propietario))->toThrow(AvanceNoReversible::class);

    // La final y el tercer puesto reciben a los de la semifinal.
    $final = $this->cuadro->partidos()->where('ronda', 3)->where('es_tercer_puesto', false)->sole();
    $tercero = $this->cuadro->partidos()->where('es_tercer_puesto', true)->sole();
    expect($final->local_id)->toBe($semifinal->refresh()->ganador_id)
        ->and($tercero->local_id)->toBe($semifinal->perdedorId());
});

test('un jugador registra con validación y el rival lo confirma', function () {
    $this->torneo->update(['resultados_por_jugadores' => ResultadosPorJugadores::ConValidacion, 'minutos_para_validar' => 30]);
    app(SortearGrupos::class)->handle($this->grupos, $this->propietario);
    app(GenerarPartidos::class)->handle($this->grupos, $this->propietario);

    $partido = $this->grupos->partidos()->orderBy('id')->firstOrFail();
    $jugadorLocal = $partido->local->jugadores->first();
    $jugadorVisitante = $partido->visitante->jugadores->first();
    $ajeno = Participante::query()->whereNotIn('id', [$partido->local_id, $partido->visitante_id])->firstOrFail()->jugadores->first();

    expect(fn () => app(RegistrarResultado::class)->handle($partido, resultadoDe(), $ajeno))->toThrow(OperacionNoPermitida::class);

    $pendiente = app(RegistrarResultado::class)->handle($partido, resultadoDe(), $jugadorLocal);

    expect($pendiente->estado)->toBe(EstadoPartido::PendienteValidacion)
        ->and($pendiente->validacion_vence_en)->not->toBeNull()
        ->and(Clasificacion::query()->where('participante_id', $partido->local_id)->sole()->puntos)->toBe(0);

    expect(fn () => app(ValidarResultado::class)->handle($pendiente, $jugadorLocal))->toThrow(OperacionNoPermitida::class);

    $validado = app(ValidarResultado::class)->handle($pendiente, $jugadorVisitante);

    expect($validado->estado)->toBe(EstadoPartido::Jugado)
        ->and(Clasificacion::query()->where('participante_id', $partido->local_id)->sole()->puntos)->toBe(2);

    $this->torneo->update(['resultados_por_jugadores' => ResultadosPorJugadores::Desactivado]);
    $otro = $this->grupos->partidos()->where('estado', EstadoPartido::Pendiente->value)->orderBy('id')->firstOrFail();
    expect(fn () => app(RegistrarResultado::class)->handle($otro, resultadoDe(), $otro->local->jugadores->first()))->toThrow(OperacionNoPermitida::class);
});

test('el torneo cambia de estado de uno en uno y con sus comprobaciones', function () {
    $torneo = Torneo::factory()->create();
    $accion = app(CambiarEstadoDelTorneo::class);

    expect(fn () => $accion->handle($torneo, EstadoTorneo::Sorteo, $this->propietario))->toThrow(OperacionNoPermitida::class)
        ->and(fn () => $accion->handle($torneo, EstadoTorneo::InscripcionAbierta, $this->propietario))->toThrow(OperacionNoPermitida::class, 'sin categorías');

    Categoria::factory()->create(['torneo_id' => $torneo->id]);
    $accion->handle($torneo, EstadoTorneo::InscripcionAbierta, $this->propietario);

    expect($torneo->refresh())->estado->toBe(EstadoTorneo::InscripcionAbierta)
        ->and($torneo->publicado_en)->not->toBeNull();

    $accion->handle($torneo, EstadoTorneo::InscripcionCerrada, $this->propietario);
    $accion->handle($torneo, EstadoTorneo::Sorteo, $this->propietario);

    expect(fn () => $accion->handle($torneo, EstadoTorneo::EnJuego, $this->propietario))->toThrow(OperacionNoPermitida::class, 'sin horarios');

    // Volver atrás también vale.
    $accion->handle($torneo, EstadoTorneo::InscripcionCerrada, $this->propietario);
    expect($torneo->refresh()->estado)->toBe(EstadoTorneo::InscripcionCerrada);
});

test('el torneo de pruebas pasa a en juego cuando tiene horarios', function () {
    app(SortearGrupos::class)->handle($this->grupos, $this->propietario);
    app(GenerarPartidos::class)->handle($this->grupos, $this->propietario);
    app(GenerarFranjas::class)->handle($this->torneo);
    app(PlanificarHorarios::class)->handle($this->torneo, $this->propietario);

    app(CambiarEstadoDelTorneo::class)->handle($this->torneo, EstadoTorneo::EnJuego, $this->propietario);

    expect($this->torneo->refresh())->estado->toBe(EstadoTorneo::EnJuego)
        ->and($this->torneo->horarios_publicados_en)->not->toBeNull();
});
