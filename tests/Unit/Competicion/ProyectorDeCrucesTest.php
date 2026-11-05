<?php

use App\Competicion\CalculadorDeClasificacion;
use App\Competicion\Datos\ConfiguracionDeEliminatoria;
use App\Competicion\Datos\CruceProyectado;
use App\Competicion\Datos\Partido;
use App\Competicion\Datos\Puntuacion;
use App\Competicion\Datos\ReglaDeClasificados;
use App\Competicion\Datos\Resultado;
use App\Competicion\Datos\Set;
use App\Competicion\ProyectorDeCruces;
use App\Enums\CriterioDeDesempate;
use App\Enums\EstadoPartido;

/**
 * Un grupo en el que cada participante gana a los que van detrás en la
 * lista: el orden de la lista es la clasificación.
 *
 * @param  list<int>  $ids
 * @return list<Partido>
 */
function grupoResuelto(int $grupo, array $ids, int &$siguienteId): array
{
    $partidos = [];
    foreach ($ids as $i => $ganador) {
        foreach (array_slice($ids, $i + 1) as $perdedor) {
            $partidos[] = new Partido(id: $siguienteId++, grupo: $grupo, local: $ganador, visitante: $perdedor, estado: EstadoPartido::Jugado, ganador: $ganador, resultado: new Resultado([new Set(6, 2), new Set(6, 2)]));
        }
    }

    return $partidos;
}

/**
 * @param  array<int, list<int>>  $grupos  Id de grupo → participantes en orden de clasificación.
 * @param  array<string, mixed>  $cuadro
 * @return array<int, CruceProyectado>
 */
function proyectar(array $grupos, array $cuadro = [], array $regla = ['por_grupo' => 2]): array
{
    $id = 1;
    $partidos = [];
    $calculador = new CalculadorDeClasificacion;
    $clasificaciones = [];

    foreach ($grupos as $grupo => $ids) {
        $partidos = [...$partidos, ...grupoResuelto($grupo, $ids, $id)];
    }
    foreach ($grupos as $grupo => $ids) {
        $clasificaciones[] = $calculador->calcular($ids, $partidos, new Puntuacion, CriterioDeDesempate::porDefecto(), [], $grupo);
    }

    return (new ProyectorDeCruces)->proyectar($clasificaciones, ReglaDeClasificados::desdeArray($regla), CriterioDeDesempate::porDefecto(), new Puntuacion, $partidos, [1 => 'A', 2 => 'B', 3 => 'C', 4 => 'D'], ConfiguracionDeEliminatoria::desdeArray($cuadro));
}

test('con cruces entre grupos cada clasificado sabe contra quién jugaría y los dos lados coinciden', function () {
    $cruces = proyectar([1 => [11, 12, 13, 14], 2 => [21, 22, 23, 24], 3 => [31, 32, 33, 34], 4 => [41, 42, 43, 44]]);

    expect($cruces)->toHaveCount(8)
        ->and($cruces[11]->origen)->toBe('1º A')
        ->and($cruces[11]->esBye())->toBeFalse()
        ->and($cruces[11]->etiqueta())->toStartWith('2º ')
        ->and($cruces[11]->etiqueta())->not->toBe('2º A');

    // Cada cruce es simétrico: si 11 juega contra X, X juega contra 11.
    foreach ($cruces as $participante => $cruce) {
        expect($cruce->participante)->toBe($participante)
            ->and($cruces[$cruce->rival]->rival)->toBe($participante)
            ->and($cruces[$cruce->rival]->etiqueta())->toBe($cruce->origen);
    }

    // Los primeros juegan contra segundos y nadie repite grupo en la primera ronda.
    foreach ($cruces as $cruce) {
        expect(mb_substr($cruce->origen, 0, 1))->not->toBe(mb_substr((string) $cruce->origenDelRival, 0, 1))
            ->and(mb_substr($cruce->origen, -1))->not->toBe(mb_substr((string) $cruce->origenDelRival, -1));
    }
});

test('con menos clasificados que huecos, los mejores tienen bye', function () {
    $cruces = proyectar([1 => [11, 12, 13], 2 => [21, 22, 23], 3 => [31, 32, 33]]);

    $byes = array_filter($cruces, fn (CruceProyectado $c): bool => $c->esBye());

    expect($cruces)->toHaveCount(6)
        ->and($byes)->toHaveCount(2)
        ->and(array_map(fn (CruceProyectado $c): string => $c->etiqueta(), $byes))->each->toBe('Bye')
        ->and(array_map(fn (CruceProyectado $c): string => mb_substr($c->origen, 0, 2), array_values($byes)))->toBe(['1º', '1º']);
});

test('con sorteo puro no se adelanta nada', function () {
    expect(proyectar([1 => [11, 12, 13, 14], 2 => [21, 22, 23, 24]], ['regla_de_cruces' => 'sorteo']))->toBe([]);
});

test('con cruces manuales que aún no casan con los clasificados tampoco', function () {
    expect(proyectar([1 => [11, 12, 13, 14], 2 => [21, 22, 23, 24]], ['regla_de_cruces' => 'manual', 'cruces_manuales' => [[99, 98], [97, 96]]]))->toBe([]);
});

test('sin clasificaciones no hay cruces', function () {
    expect((new ProyectorDeCruces)->proyectar([], new ReglaDeClasificados, CriterioDeDesempate::porDefecto(), new Puntuacion, [], [], new ConfiguracionDeEliminatoria))->toBe([]);
});
