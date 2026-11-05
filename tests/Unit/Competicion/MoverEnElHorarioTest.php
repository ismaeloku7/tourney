<?php

use App\Competicion\Errores\OperacionNoPermitida;
use App\Competicion\MoverEnElHorario;

/** Marca de tiempo de una hora del sábado de pruebas. */
function hm(string $hora): int
{
    return (new DateTimeImmutable("2026-11-14 {$hora}:00"))->getTimestamp();
}

/** Un partido de 70 minutos (10 de calentamiento) en una pista. */
function ocupa(int $id, int $pista, string $hora, array $jugadores = [], bool $movible = true, int $minutos = 70): array
{
    return ['id' => $id, 'pista' => $pista, 'inicio' => hm($hora), 'fin' => hm($hora) + $minutos * 60, 'movible' => $movible, 'jugadores' => $jugadores];
}

/** @param  array<int, array{pista: int, inicio: int, fin: int}>  $cambios */
function comoQuedan(array $cambios): array
{
    ksort($cambios);

    return array_map(fn (array $c): string => $c['pista'].' '.date('H:i', $c['inicio']), $cambios);
}

test('al soltarlo en otra pista se pega al anterior, empuja a los de detrás y en la suya suben los que iban pegados', function () {
    $horario = [
        ocupa(1, 1, '09:00'), ocupa(2, 1, '10:10'), ocupa(3, 1, '11:20'),
        ocupa(4, 2, '09:00'), ocupa(5, 2, '10:10'),
    ];

    // Se suelta el 2 en la pista 2 a las 10:15: dentro del 5, así que va detrás.
    $resultado = (new MoverEnElHorario)->mover($horario, 2, 2, hm('10:15'));
    expect(comoQuedan($resultado['cambios']))->toBe([2 => '2 11:20', 3 => '1 10:10']);

    // A las 09:20 (dentro del 4): detrás del 4 y el 5 se empuja.
    $resultado = (new MoverEnElHorario)->mover($horario, 2, 2, hm('09:20'));
    expect(comoQuedan($resultado['cambios']))->toBe([2 => '2 10:10', 3 => '1 10:10', 5 => '2 11:20']);

    // A las 11:30 en la 2, a 10 minutos del final del 5: se pega a él.
    $resultado = (new MoverEnElHorario)->mover($horario, 2, 2, hm('11:30'));
    expect(comoQuedan($resultado['cambios']))->toBe([2 => '2 11:20', 3 => '1 10:10']);
});

test('soltado sobre otro partido, se intercambian', function () {
    $horario = [ocupa(1, 1, '09:00'), ocupa(2, 1, '10:10'), ocupa(3, 2, '09:00')];

    $resultado = (new MoverEnElHorario)->intercambiar($horario, 2, 3);

    expect(comoQuedan($resultado['cambios']))->toBe([2 => '2 09:00', 3 => '1 10:10']);
});

test('lo jugado y los bloqueos no se mueven: si estorban, no se mueve nada', function () {
    $horario = [ocupa(1, 1, '09:00'), ocupa(2, 2, '09:00'), ocupa(-7, 2, '10:10', movible: false)];

    expect(fn () => (new MoverEnElHorario)->mover($horario, 1, 2, hm('09:30')))->toThrow(OperacionNoPermitida::class);
    expect(fn () => (new MoverEnElHorario)->mover([ocupa(1, 1, '09:00', movible: false)], 1, 2, hm('09:00')))->toThrow(OperacionNoPermitida::class);
});

test('avisa si alguien queda jugando a la vez o tres seguidos', function () {
    $horario = [
        ocupa(1, 1, '09:00', [1, 2]), ocupa(2, 1, '10:10', [1, 3]), ocupa(3, 2, '12:00', [1, 4]),
        ocupa(4, 2, '09:00', [5, 6]),
    ];

    // El 3 a las 10:00 en la 2 (pegado al 4): a la vez que el 2 para el jugador 1.
    $aLaVez = (new MoverEnElHorario)->mover($horario, 3, 2, hm('10:00'), 10);
    // El 3 a las 11:20 en la 1, pegado al 2: el jugador 1 juega tres seguidos.
    $tres = (new MoverEnElHorario)->mover($horario, 3, 1, hm('11:25'), 10);

    expect($aLaVez['avisos'])->toBe([['partido' => 3, 'tipo' => 'a_la_vez']])
        ->and($tres['avisos'])->toBe([['partido' => 3, 'tipo' => 'tres_seguidos']]);
});
