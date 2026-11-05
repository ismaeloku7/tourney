<?php

use App\Competicion\Datos\PartidoEnCola;
use App\Competicion\EstimadorDeHorario;

function momento(string $hora): DateTimeImmutable
{
    return new DateTimeImmutable("2026-11-14 {$hora}:00");
}

/** @return list<PartidoEnCola> */
function colaDeTres(): array
{
    return [
        new PartidoEnCola(id: 1, orden: 1, local: 11, visitante: 12, duracionMinutos: 60),
        new PartidoEnCola(id: 2, orden: 2, local: 21, visitante: 22, duracionMinutos: 60),
        new PartidoEnCola(id: 3, orden: 3, local: 11, visitante: 21, duracionMinutos: 60),
    ];
}

test('sin nada jugado, el primero a la apertura más el calentamiento largo y los demás encadenados', function () {
    $estimados = (new EstimadorDeHorario)->estimar(colaDeTres(), momento('09:00'), momento('08:00'), 15, 5);

    // 1: 09:15–10:15. 2: parejas nuevas, 10:15 + 15 = 10:30–11:30. 3: las dos ya han jugado, 11:30 + 5 = 11:35.
    expect(array_map(fn (DateTimeImmutable $d): string => $d->format('H:i'), $estimados))->toBe([1 => '09:15', 2 => '10:30', 3 => '11:35']);
});

test('un partido empezado fija su hora real y arrastra a los siguientes; uno cerrado solo cuenta por cuándo acabó', function () {
    $cola = colaDeTres();
    $cola[0] = new PartidoEnCola(id: 1, orden: 1, local: 11, visitante: 12, duracionMinutos: 60, empezadoEn: momento('09:30'));

    $estimados = (new EstimadorDeHorario)->estimar($cola, momento('09:00'), momento('09:40'), 15, 5);
    expect(array_map(fn (DateTimeImmutable $d): string => $d->format('H:i'), $estimados))->toBe([1 => '09:30', 2 => '10:45', 3 => '11:50']);

    $cola[0] = new PartidoEnCola(id: 1, orden: 1, local: 11, visitante: 12, duracionMinutos: 60, empezadoEn: momento('09:30'), terminadoEn: momento('10:20'), cerrado: true);
    $estimados = (new EstimadorDeHorario)->estimar($cola, momento('09:00'), momento('10:20'), 15, 5);
    expect($estimados)->not->toHaveKey(1)
        ->and($estimados[2]->format('H:i'))->toBe('10:35')
        ->and($estimados[3]->format('H:i'))->toBe('11:40');
});

test('si el anterior se alarga, el siguiente no empieza antes de ahora; y quien ya jugó en otra pista calienta poco', function () {
    $cola = colaDeTres();
    $cola[0] = new PartidoEnCola(id: 1, orden: 1, local: 11, visitante: 12, duracionMinutos: 60, empezadoEn: momento('09:15'));

    // Son las 10:30 y el primero sigue en pista: el segundo, como pronto, a las 10:30 + 15.
    $estimados = (new EstimadorDeHorario)->estimar($cola, momento('09:00'), momento('10:30'), 15, 5);
    expect($estimados[2]->format('H:i'))->toBe('10:45');

    // Si 21 y 22 ya jugaron en otra pista, el calentamiento es el corto.
    $estimados = (new EstimadorDeHorario)->estimar($cola, momento('09:00'), momento('10:30'), 15, 5, yaHanJugado: [21, 22]);
    expect($estimados[2]->format('H:i'))->toBe('10:35');
});

test('la cola se ordena por el orden en pista aunque llegue desordenada', function () {
    $cola = array_reverse(colaDeTres());

    expect((new EstimadorDeHorario)->estimar($cola, momento('09:00'), momento('08:00'), 15, 5)[1]->format('H:i'))->toBe('09:15');
});
