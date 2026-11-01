<?php

use App\Enums\Ajuste;
use App\Models\Categoria;
use App\Models\Fase;
use App\Models\FormatoPartido;
use App\Models\Grupo;
use App\Models\Inscripcion;
use App\Models\Jugador;
use App\Models\Organizador;
use App\Models\Participante;
use App\Models\Pista;
use App\Models\Sede;
use App\Models\Torneo;
use App\Models\ValorDeAjuste;
use Database\Seeders\PlanesSeeder;

beforeEach(function () {
    $this->seed(PlanesSeeder::class);
    usarOrganizador(Organizador::factory()->create(['id' => 'demo', 'nombre' => 'Club Demo']));
});

test('una pareja nace con dos jugadores y su nombre sale de los apellidos', function () {
    $pareja = Participante::factory()->create();

    expect($pareja->jugadores)->toHaveCount(2)
        ->and($pareja->nombre)->toBe(Participante::nombreDesdeJugadores(...$pareja->jugadores->all()))
        ->and($pareja->nombre)->toContain(' / ')
        ->and($pareja->idsDeJugadores())->toHaveCount(2);

    $ana = Jugador::factory()->create(['nombre' => 'Ana', 'apellidos' => 'García']);
    $luz = Jugador::factory()->create(['nombre' => 'Luz', 'apellidos' => 'López']);
    $conocida = Participante::factory()->conJugadores($ana, $luz)->create();

    expect($conocida->nombre)->toBe('García / López')
        ->and($conocida->jugadores->pluck('id')->all())->toBe([$ana->id, $luz->id]);
});

test('los ajustes se leen con su valor por defecto y se fijan por clave', function () {
    expect(ValorDeAjuste::obtener(Ajuste::ZonaHoraria))->toBe('Europe/Madrid')
        ->and(ValorDeAjuste::obtener(Ajuste::NombrePublico))->toBeNull();

    ValorDeAjuste::fijar([Ajuste::NombrePublico->value => 'Pádel Demo', Ajuste::PrivacidadAliasPorDefecto->value => true]);

    expect(ValorDeAjuste::obtener(Ajuste::NombrePublico))->toBe('Pádel Demo')
        ->and(ValorDeAjuste::obtener(Ajuste::PrivacidadAliasPorDefecto))->toBeTrue()
        ->and(fn () => ValorDeAjuste::fijar(['inventado' => 1]))->toThrow(ValueError::class);
});

test('borrar un torneo manda a la papelera lo que cuelga de él y restaurarlo lo devuelve', function () {
    $torneo = Torneo::factory()->create();
    $categoria = Categoria::factory()->create(['torneo_id' => $torneo->id]);
    $pareja = Participante::factory()->create(['categoria_id' => $categoria->id]);
    Inscripcion::factory()->create(['participante_id' => $pareja->id]);
    Fase::factory()->create(['categoria_id' => $categoria->id]);

    $torneo->delete();

    expect(Categoria::query()->count())->toBe(0)
        ->and(Participante::query()->count())->toBe(0)
        ->and(Inscripcion::query()->count())->toBe(0)
        ->and(Fase::query()->count())->toBe(0)
        ->and(Categoria::onlyTrashed()->count())->toBe(1);

    $torneo->restore();

    expect(Categoria::query()->count())->toBe(1)
        ->and(Participante::query()->count())->toBe(1)
        ->and(Inscripcion::query()->count())->toBe(1)
        ->and(Fase::query()->count())->toBe(1);
});

test('borrar una sede deja sus pistas sin sede, y borrar un formato deja a las categorías sin él', function () {
    $sede = Sede::factory()->create();
    $pista = Pista::factory()->create(['sede_id' => $sede->id]);
    $formato = FormatoPartido::factory()->create();
    $categoria = Categoria::factory()->create(['formato_partido_id' => $formato->id]);

    $sede->delete();
    $formato->delete();

    expect($pista->fresh()->sede_id)->toBeNull()
        ->and($categoria->fresh()->formato_partido_id)->toBeNull();
});

test('el formato efectivo baja de la ronda a la fase, a la categoría y al predeterminado', function () {
    $predeterminado = FormatoPartido::factory()->predeterminado()->create(['nombre' => 'Predeterminado']);
    $deCategoria = FormatoPartido::factory()->create(['nombre' => 'De categoría']);
    $deFase = FormatoPartido::factory()->create(['nombre' => 'De fase']);

    $categoria = Categoria::factory()->create();
    $fase = Fase::factory()->create(['categoria_id' => $categoria->id]);

    expect($fase->formatoEfectivo()->nombre)->toBe('Predeterminado');

    $categoria->update(['formato_partido_id' => $deCategoria->id]);
    expect($fase->fresh()->formatoEfectivo()->nombre)->toBe('De categoría');

    $fase->update(['formato_partido_id' => $deFase->id]);
    expect($fase->fresh()->formatoEfectivo()->nombre)->toBe('De fase');

    $siguiente = Fase::factory()->eliminatoria()->create(['categoria_id' => $categoria->id]);
    expect($fase->siguiente()?->id)->toBe($siguiente->id)
        ->and($siguiente->siguiente())->toBeNull();
});

test('los grupos se llaman por letras y un jugador enseña alias solo si el torneo lo pide', function () {
    expect(Grupo::nombrePorOrden(1))->toBe('A')
        ->and(Grupo::nombrePorOrden(26))->toBe('Z')
        ->and(Grupo::nombrePorOrden(27))->toBe('AA');

    $jugador = Jugador::factory()->conAlias('Rayo')->create(['nombre' => 'Ana', 'apellidos' => 'García', 'fecha_nacimiento' => now()->subYears(12)]);

    expect($jugador->nombrePublico(false))->toBe('Ana García')
        ->and($jugador->nombrePublico(true))->toBe('Rayo')
        ->and($jugador->esMenorDeCatorce())->toBeTrue()
        ->and($jugador->toArray())->not->toHaveKeys(['email', 'telefono']);
});
