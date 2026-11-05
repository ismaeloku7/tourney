<?php

use App\Competicion\Datos\PartidoEnDirecto;
use App\Competicion\PistasEnDirecto;

/** Marca de tiempo de una hora del sábado de pruebas. */
function hs(string $hora): int
{
    return (new DateTimeImmutable("2026-11-14 {$hora}:00"))->getTimestamp();
}

/** Un partido de 70 minutos (calentamiento incluido) entre los jugadores dados. */
function enPista(int $id, int $pista, string $hora, array $jugadores, ?string $acabo = null, ?string $previsto = null, array $dependeDe = []): PartidoEnDirecto
{
    return new PartidoEnDirecto($id, $pista, hs($hora), 70, $jugadores, $dependeDe, $acabo !== null, $acabo !== null ? hs($acabo) : null, hs($previsto ?? $hora));
}

/** @param  array<int, array{0: int, 1: int}>  $horas */
function horasDe(array $horas): array
{
    ksort($horas);

    return array_map(fn (array $h): string => date('H:i', $h[0]), $horas);
}

test('la pista que acaba antes llama ya al siguiente y la que se alarga empuja a los suyos', function () {
    $partidos = [
        enPista(1, 1, '09:00', [1, 2, 3, 4], acabo: '09:50'),
        enPista(2, 1, '10:10', [5, 6, 7, 8]),
        enPista(3, 2, '09:00', [11, 12, 13, 14]),
        enPista(4, 2, '10:10', [15, 16, 17, 18]),
    ];

    // A las 10:30 la pista 2 sigue con su primer partido (iba a acabar a las 10:10).
    $horas = (new PistasEnDirecto)->recalcular($partidos, hs('09:50'), 30);
    expect(horasDe($horas))->toBe([2 => '09:50', 3 => '09:00', 4 => '10:10']);

    $horas = (new PistasEnDirecto)->recalcular($partidos, hs('10:30'), 30);
    expect($horas[3][1])->toBe(hs('10:30'))
        ->and(date('H:i', $horas[4][0]))->toBe('10:30');
});

test('el primer partido del día de una pista no se adelanta', function () {
    $horas = (new PistasEnDirecto)->recalcular([enPista(1, 1, '12:00', [1, 2, 3, 4])], hs('09:00'), 30);

    expect(horasDe($horas))->toBe([1 => '12:00']);
});

test('el descanso de un jugador manda aunque su pista quede libre', function () {
    $partidos = [
        enPista(1, 1, '09:00', [1, 2, 3, 4], acabo: '10:30'),
        enPista(2, 2, '09:00', [5, 6, 7, 8], acabo: '09:40'),
        // En la pista 2, después, juega el jugador 1: tiene 30 de descanso desde las 10:30.
        enPista(3, 2, '10:10', [1, 9, 10, 11]),
    ];

    $horas = (new PistasEnDirecto)->recalcular($partidos, hs('10:30'), 30);

    expect(date('H:i', $horas[3][0]))->toBe('11:00');
});

test('un partido de la ronda siguiente espera a los que le dan sus parejas, más el descanso', function () {
    $partidos = [
        enPista(1, 1, '09:00', [1, 2, 3, 4]),
        enPista(2, 2, '09:00', [5, 6, 7, 8]),
        new PartidoEnDirecto(3, 1, hs('10:40'), 70, [], [1, 2], false, null, hs('10:40')),
    ];

    $horas = (new PistasEnDirecto)->recalcular($partidos, hs('09:30'), 30);

    expect(date('H:i', $horas[3][0]))->toBe('10:40');

    // Si el de la pista 2 se alarga hasta las 10:40, la final espera a las 11:10.
    $horas = (new PistasEnDirecto)->recalcular([...array_slice($partidos, 0, 1), enPista(2, 2, '09:00', [5, 6, 7, 8], acabo: '10:40'), $partidos[2]], hs('10:40'), 30);
    expect(date('H:i', $horas[3][0]))->toBe('11:10');
});

test('el retraso de cada pista es su próximo partido contra la hora prevista', function () {
    $partidos = [
        enPista(1, 1, '09:00', [1, 2, 3, 4], acabo: '10:40'),
        enPista(2, 1, '10:10', [5, 6, 7, 8]),
        enPista(3, 2, '09:00', [11, 12, 13, 14], acabo: '09:50'),
        enPista(4, 2, '10:10', [15, 16, 17, 18]),
    ];
    $motor = new PistasEnDirecto;
    $horas = $motor->recalcular($partidos, hs('09:55'), 30);

    expect($motor->retrasos($partidos, $horas, hs('09:55')))->toBe([1 => 30]);
});

test('con una pista 20 minutos tarde propone pasar a otra uno de sus partidos con tiempo para avisar', function () {
    $partidos = [
        enPista(1, 1, '09:00', [1, 2, 3, 4], acabo: '09:50'),
        enPista(2, 1, '10:10', [5, 6, 7, 8]),
        enPista(3, 1, '11:20', [9, 10, 11, 12]),
        enPista(4, 2, '09:00', [13, 14, 15, 16], acabo: '10:40'),
        enPista(5, 2, '10:10', [17, 18, 19, 20]),
        enPista(6, 2, '11:20', [21, 22, 23, 24]),
        enPista(7, 2, '12:30', [25, 26, 27, 28]),
        enPista(8, 2, '13:40', [29, 30, 31, 32]),
    ];

    $sugerencia = (new PistasEnDirecto)->sugerencia($partidos, hs('10:40'), 30);

    expect($sugerencia)->not->toBeNull()
        ->and($sugerencia->desdePista)->toBe(2)
        ->and($sugerencia->haciaPista)->toBe(1)
        ->and($sugerencia->retrasoDeLaPista)->toBe(30)
        // Ni el siguiente (5) ni uno sin 30 minutos de aviso.
        ->and($sugerencia->partido)->not->toBe(5)
        ->and($sugerencia->inicio)->toBeGreaterThanOrEqual(hs('11:10'))
        ->and($sugerencia->ganaRetraso)->toBeGreaterThan(0);
});

test('sin pistas con 20 minutos de retraso no propone nada', function () {
    $partidos = [
        enPista(1, 1, '09:00', [1, 2, 3, 4], acabo: '10:20'),
        enPista(2, 1, '10:10', [5, 6, 7, 8]),
        enPista(3, 1, '11:20', [9, 10, 11, 12]),
        enPista(4, 2, '09:00', [13, 14, 15, 16], acabo: '10:10'),
        enPista(5, 2, '10:10', [17, 18, 19, 20]),
    ];

    expect((new PistasEnDirecto)->sugerencia($partidos, hs('10:20'), 30))->toBeNull();
});
