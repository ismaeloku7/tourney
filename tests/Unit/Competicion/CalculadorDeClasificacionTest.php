<?php

use App\Competicion\CalculadorDeClasificacion;
use App\Competicion\Datos\Partido;
use App\Competicion\Datos\Puntuacion;
use App\Competicion\Datos\Resultado;
use App\Competicion\Datos\Set;
use App\Enums\CriterioDeDesempate;
use App\Enums\EstadoPartido;
use App\Enums\ResultadoEspecial;

/** @param  list<array{0: int, 1: int}>  $sets  Juegos por set, desde el local. */
function jugado(int $id, int $local, int $visitante, array $sets): Partido
{
    $resultado = new Resultado(array_map(fn (array $s): Set => new Set($s[0], $s[1]), $sets));
    $ganador = $resultado->setsGanadosPorLocal() > $resultado->setsGanadosPorVisitante() ? $local : $visitante;

    return new Partido(id: $id, grupo: 1, local: $local, visitante: $visitante, estado: EstadoPartido::Jugado, ganador: $ganador, resultado: $resultado);
}

function pendiente(int $id, int $local, int $visitante): Partido
{
    return new Partido(id: $id, grupo: 1, local: $local, visitante: $visitante);
}

function clasificar(array $participantes, array $partidos, array $sorteos = [], ?array $criterios = null, ?Puntuacion $puntuacion = null)
{
    return (new CalculadorDeClasificacion)->calcular($participantes, $partidos, $puntuacion ?? new Puntuacion, $criterios ?? CriterioDeDesempate::porDefecto(), $sorteos, 1);
}

test('la tabla suma puntos, partidos, sets y juegos', function () {
    $clasificacion = clasificar([1, 2, 3], [
        jugado(1, 1, 2, [[6, 3], [6, 4]]),
        jugado(2, 2, 3, [[6, 2], [3, 6], [7, 5]]),
        pendiente(3, 1, 3),
    ]);

    $uno = $clasificacion->linea(1);
    $dos = $clasificacion->linea(2);
    $tres = $clasificacion->linea(3);

    expect($clasificacion->completa)->toBeFalse()
        ->and($clasificacion->participantesEnOrden())->toBe([1, 2, 3])
        ->and($uno)->posicion->toBe(1)->puntos->toBe(2)->pj->toBe(1)->pg->toBe(1)->pp->toBe(0)->setsFavor->toBe(2)->setsContra->toBe(0)->juegosFavor->toBe(12)->juegosContra->toBe(7)
        ->and($dos)->posicion->toBe(2)->puntos->toBe(2)->pj->toBe(2)->pg->toBe(1)->pp->toBe(1)->setsFavor->toBe(2)->setsContra->toBe(3)
        ->and($tres)->posicion->toBe(3)->puntos->toBe(0)->pj->toBe(1)->juegosFavor->toBe(13)->juegosContra->toBe(16)
        // 1 y 2 empatan a 2 puntos y 1 ganó el partido entre ambos.
        ->and($uno->criterioAplicado)->toBe(CriterioDeDesempate::EnfrentamientoDirecto)
        ->and($dos->criterioAplicado)->toBe(CriterioDeDesempate::Puntos)
        ->and($tres->criterioAplicado)->toBeNull();
});

test('un empate a dos se resuelve por el enfrentamiento directo', function () {
    // 1 gana sus tres partidos. 2 y 3 empatan a 2 puntos: 2 tiene mejor
    // diferencia de sets y juegos, pero 3 ganó el partido entre ambos.
    $clasificacion = clasificar([1, 2, 3, 4], [
        jugado(1, 1, 2, [[6, 0], [6, 0]]),
        jugado(3, 1, 3, [[6, 4], [6, 4]]),
        jugado(5, 1, 4, [[6, 1], [6, 1]]),
        jugado(4, 2, 4, [[6, 0], [6, 0]]),
        jugado(6, 3, 2, [[6, 4], [6, 4]]),
        pendiente(7, 3, 4),
    ]);

    expect($clasificacion->participantesEnOrden())->toBe([1, 3, 2, 4])
        ->and($clasificacion->linea(3)->criterioAplicado)->toBe(CriterioDeDesempate::EnfrentamientoDirecto)
        ->and($clasificacion->completa)->toBeFalse();
});

test('un triple empate con victorias circulares se resuelve por diferencia de sets', function () {
    $clasificacion = clasificar([1, 2, 3], [
        jugado(1, 1, 2, [[6, 0], [6, 0]]),            // 1 gana 2-0
        jugado(2, 2, 3, [[6, 4], [4, 6], [6, 4]]),    // 2 gana 2-1
        jugado(3, 3, 1, [[6, 4], [4, 6], [6, 4]]),    // 3 gana 2-1
    ]);

    // Sets: 1 → 3-2 (+1); 2 → 2-3 (-1); 3 → 3-3 (0). Lo decide la mini-liga
    // entre los tres (que aquí es el grupo entero) por diferencia de sets, y
    // se anota como resuelto por el enfrentamiento entre empatados.
    expect($clasificacion->participantesEnOrden())->toBe([1, 3, 2])
        ->and($clasificacion->linea(1)->criterioAplicado)->toBe(CriterioDeDesempate::EnfrentamientoDirecto);
});

test('con tres o más empatados la mini-liga va antes que el siguiente criterio', function () {
    // Grupo de 4: 1, 2 y 3 empatan a 4 puntos; 4 pierde todo.
    // Entre ellos: 1 gana a 2, 2 gana a 3, 3 gana a 1 (mini-liga empatada a 2),
    // pero 3 ganó sus partidos de la mini-liga por más sets: 3 > 1 > 2 en la mini-liga.
    $clasificacion = clasificar([1, 2, 3, 4], [
        jugado(1, 1, 2, [[6, 4], [4, 6], [6, 4]]),    // 1 gana 2-1
        jugado(2, 2, 3, [[6, 4], [4, 6], [6, 4]]),    // 2 gana 2-1
        jugado(3, 3, 1, [[6, 0], [6, 0]]),            // 3 gana 2-0
        jugado(4, 1, 4, [[6, 0], [6, 0]]),
        jugado(5, 2, 4, [[6, 0], [6, 0]]),
        jugado(6, 4, 3, [[0, 6], [1, 6]]),
    ]);

    // Mini-liga: 3 → sets 3-1 (+2); 1 → 2-3 (-1); 2 → 3-3... 2: contra 1: 1-2, contra 3: 2-1 → 3-3 (0).
    expect($clasificacion->participantesEnOrden())->toBe([3, 2, 1, 4])
        ->and($clasificacion->linea(3)->criterioAplicado)->toBe(CriterioDeDesempate::EnfrentamientoDirecto)
        ->and($clasificacion->linea(1)->criterioAplicado)->toBe(CriterioDeDesempate::Puntos);
});

test('el sorteo es estable entre recálculos cuando se le devuelve el sorteo previo', function () {
    $partidos = [
        jugado(1, 1, 2, [[6, 4], [6, 4]]),
        jugado(2, 2, 1, [[6, 4], [6, 4]]),
    ];
    $criterios = [CriterioDeDesempate::Puntos, CriterioDeDesempate::Sorteo];

    $primera = clasificar([1, 2], $partidos, [], $criterios);

    expect($primera->linea(1)->sorteo)->toBeInt()
        ->and($primera->linea(2)->sorteo)->toBeInt()
        ->and($primera->linea(1)->sorteo)->not->toBe($primera->linea(2)->sorteo)
        ->and($primera->lineas[0]->criterioAplicado)->toBe(CriterioDeDesempate::Sorteo);

    $sorteos = [1 => $primera->linea(1)->sorteo, 2 => $primera->linea(2)->sorteo];
    $segunda = clasificar([1, 2], $partidos, $sorteos, $criterios);

    expect($segunda->participantesEnOrden())->toBe($primera->participantesEnOrden())
        ->and($segunda->linea(1)->sorteo)->toBe($sorteos[1]);
});

test('un walkover suma como victoria con 2-0 en sets y 6-0 por set', function () {
    $walkover = new Partido(id: 1, grupo: 1, local: 1, visitante: 2, estado: EstadoPartido::Walkover, ganador: 2, resultado: new Resultado([], ResultadoEspecial::Walkover, false));
    $clasificacion = clasificar([1, 2], [$walkover]);

    expect($clasificacion->linea(2))->puntos->toBe(2)->pg->toBe(1)->setsFavor->toBe(2)->juegosFavor->toBe(12)
        ->and($clasificacion->linea(1))->puntos->toBe(0)->pp->toBe(1)->setsContra->toBe(2)->juegosContra->toBe(12)
        ->and($clasificacion->completa)->toBeTrue();

    $propia = clasificar([1, 2], [$walkover], [], null, new Puntuacion(walkoverFavor: 3, walkoverContra: 1, walkoverSets: 1, walkoverJuegosPorSet: 4));

    expect($propia->linea(2))->puntos->toBe(3)->setsFavor->toBe(1)->juegosFavor->toBe(4)
        ->and($propia->linea(1)->puntos)->toBe(1);
});

test('un no presentado cuenta como jugado para los dos sin victoria ni derrota', function () {
    $noPresentado = new Partido(id: 1, grupo: 1, local: 1, visitante: 2, estado: EstadoPartido::Walkover, resultado: new Resultado([], ResultadoEspecial::NoPresentado));
    $clasificacion = clasificar([1, 2], [$noPresentado], [], null, new Puntuacion(noJugado: 1));

    expect($clasificacion->linea(1))->puntos->toBe(1)->pj->toBe(1)->pg->toBe(0)->pp->toBe(0)
        ->and($clasificacion->linea(2)->puntos)->toBe(1);
});

test('completa solo con todos los partidos cerrados, y un anulado no cierra', function () {
    $anulado = new Partido(id: 2, grupo: 1, local: 2, visitante: 3, estado: EstadoPartido::Anulado);

    expect(clasificar([1, 2, 3], [jugado(1, 1, 2, [[6, 1], [6, 1]])])->completa)->toBeTrue()
        ->and(clasificar([1, 2, 3], [jugado(1, 1, 2, [[6, 1], [6, 1]]), $anulado])->completa)->toBeFalse()
        ->and(clasificar([1, 2, 3], [])->completa)->toBeFalse();
});

test('los puntos por set ganado se suman a los de victoria', function () {
    $clasificacion = clasificar([1, 2], [jugado(1, 1, 2, [[6, 4], [4, 6], [6, 4]])], [], null, new Puntuacion(victoria: 2, derrota: 0, porSetGanado: 1));

    expect($clasificacion->linea(1)->puntos)->toBe(4)
        ->and($clasificacion->linea(2)->puntos)->toBe(1);
});

test('un partido de alguien de fuera del grupo no cuenta, y todos aparecen aunque no hayan jugado', function () {
    $clasificacion = clasificar([1, 2], [jugado(1, 1, 9, [[6, 1], [6, 1]])]);

    expect($clasificacion->lineas)->toHaveCount(2)
        ->and($clasificacion->linea(1)->pj)->toBe(0)
        ->and($clasificacion->grupo)->toBe(1);
});
