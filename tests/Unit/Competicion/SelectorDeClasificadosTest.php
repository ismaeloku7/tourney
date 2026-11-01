<?php

use App\Competicion\CalculadorDeClasificacion;
use App\Competicion\Datos\Clasificado;
use App\Competicion\Datos\Partido;
use App\Competicion\Datos\Puntuacion;
use App\Competicion\Datos\ReglaDeClasificados;
use App\Competicion\Datos\Resultado;
use App\Competicion\Datos\Set;
use App\Competicion\SelectorDeClasificados;
use App\Enums\CriterioDeDesempate;
use App\Enums\EstadoPartido;

/**
 * Un grupo en el que cada participante gana a todos los que van detrás
 * de él en la lista, con los sets que se indiquen por victoria.
 *
 * @param  list<int>  $ids
 * @return list<Partido>
 */
function grupoJerarquico(int $grupo, array $ids, int &$siguienteId, array $setsPorVictoria = [[6, 2], [6, 2]]): array
{
    $partidos = [];
    foreach ($ids as $i => $ganador) {
        foreach (array_slice($ids, $i + 1) as $perdedor) {
            $resultado = new Resultado(array_map(fn (array $s): Set => new Set($s[0], $s[1]), $setsPorVictoria));
            $partidos[] = new Partido(id: $siguienteId++, grupo: $grupo, local: $ganador, visitante: $perdedor, estado: EstadoPartido::Jugado, ganador: $ganador, resultado: $resultado);
        }
    }

    return $partidos;
}

function seleccionar(array $grupos, array $regla, array $partidos)
{
    $calculador = new CalculadorDeClasificacion;
    $clasificaciones = [];
    foreach ($grupos as $grupo => $ids) {
        $clasificaciones[] = $calculador->calcular($ids, $partidos, new Puntuacion, CriterioDeDesempate::porDefecto(), [], $grupo);
    }

    return (new SelectorDeClasificados($calculador))->seleccionar($clasificaciones, ReglaDeClasificados::desdeArray($regla), CriterioDeDesempate::porDefecto(), new Puntuacion, $partidos, [1 => 'A', 2 => 'B', 3 => 'C', 4 => 'D']);
}

test('dos por grupo de cuatro grupos: primero todos los primeros, luego los segundos', function () {
    $id = 1;
    $partidos = array_merge(
        grupoJerarquico(1, [11, 12, 13, 14], $id),
        grupoJerarquico(2, [21, 22, 23, 24], $id),
        grupoJerarquico(3, [31, 32, 33, 34], $id),
        grupoJerarquico(4, [41, 42, 43, 44], $id),
    );

    $clasificados = seleccionar([1 => [11, 12, 13, 14], 2 => [21, 22, 23, 24], 3 => [31, 32, 33, 34], 4 => [41, 42, 43, 44]], ['por_grupo' => 2], $partidos);

    expect($clasificados)->toHaveCount(8)
        ->and(array_map(fn (Clasificado $c): int => $c->participante, $clasificados))->toBe([11, 21, 31, 41, 12, 22, 32, 42])
        ->and(array_map(fn (Clasificado $c): int => $c->orden, $clasificados))->toBe(range(1, 8))
        ->and($clasificados[0]->origen)->toBe('1º A')
        ->and($clasificados[4]->origen)->toBe('2º A')
        ->and($clasificados[4]->posicionEnGrupo)->toBe(2);
});

test('los mejores terceros se comparan entre sí, descartando el partido contra el último del grupo grande', function () {
    $id = 1;
    // Grupo A de 4: el tercero gana solo al cuarto, 2-0.
    // Grupo B de 5: el tercero gana al cuarto y al quinto, 2-0 cada uno: más puntos y sets solo por tener un rival más.
    // Grupo C de 4: el tercero gana al cuarto 2-1 (peor diferencia de sets que el de A).
    $partidos = array_merge(
        grupoJerarquico(1, [11, 12, 13, 14], $id),
        grupoJerarquico(2, [21, 22, 23, 24, 25], $id),
        grupoJerarquico(3, [31, 32, 33, 34], $id, [[6, 2], [2, 6], [6, 2]]),
    );

    $clasificados = seleccionar([1 => [11, 12, 13, 14], 2 => [21, 22, 23, 24, 25], 3 => [31, 32, 33, 34]], ['por_grupo' => 2, 'mejores_de_posicion' => 3, 'cuantos_mejores' => 2], $partidos);

    $mejores = array_slice($clasificados, 6);

    // Sin el ajuste, 23 iría el primero con 4 puntos. Con él se queda en 2,
    // como 13 y 33: 33 tiene mejor diferencia de sets (-1 frente a -2) y
    // entre 13 y 23, iguales en todo, decide el id.
    expect($clasificados)->toHaveCount(8)
        ->and(array_map(fn (Clasificado $c): int => $c->participante, $mejores))->toBe([33, 13])
        ->and($mejores[0]->origen)->toBe('mejor 3º (C)')
        ->and($mejores[0]->posicionEnGrupo)->toBe(3)
        ->and(array_map(fn (Clasificado $c): int => $c->orden, $mejores))->toBe([7, 8]);
});

test('los mejores segundos también valen, y el orden global sigue a los primeros', function () {
    $id = 1;
    $partidos = array_merge(
        grupoJerarquico(1, [11, 12, 13], $id, [[6, 0], [6, 0]]),
        grupoJerarquico(2, [21, 22, 23], $id, [[6, 4], [6, 4]]),
        grupoJerarquico(3, [31, 32, 33], $id, [[7, 5], [7, 5]]),
    );

    $clasificados = seleccionar([1 => [11, 12, 13], 2 => [21, 22, 23], 3 => [31, 32, 33]], ['por_grupo' => 1, 'mejores_de_posicion' => 2, 'cuantos_mejores' => 1], $partidos);

    // Entre primeros: 11 arrasa; 21 y 31 empatan en todo menos en juegos
    // ganados (24 frente a 28). Entre segundos, 32 tiene más juegos ganados.
    expect(array_map(fn (Clasificado $c): int => $c->participante, $clasificados))->toBe([11, 31, 21, 32])
        ->and($clasificados[3]->origen)->toBe('mejor 2º (C)');
});

test('entre primeros de distintos grupos, el orden lo dan los criterios de estadística', function () {
    $id = 1;
    $partidos = array_merge(
        grupoJerarquico(1, [11, 12, 13], $id, [[6, 4], [6, 4]]),
        grupoJerarquico(2, [21, 22, 23], $id, [[6, 0], [6, 0]]),
    );

    $clasificados = seleccionar([1 => [11, 12, 13], 2 => [21, 22, 23]], ['por_grupo' => 1], $partidos);

    expect(array_map(fn (Clasificado $c): int => $c->participante, $clasificados))->toBe([21, 11]);
});
