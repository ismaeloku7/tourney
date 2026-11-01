<?php

use App\Competicion\Datos\Asignacion;
use App\Competicion\Datos\Conflicto;
use App\Competicion\Datos\FranjaDisponible;
use App\Competicion\Datos\Intervalo;
use App\Competicion\Datos\PartidoAPlanificar;
use App\Competicion\Datos\RestriccionesDePlanificacion;
use App\Competicion\Planificador;

function hora(string $hora, string $dia = '2026-11-14'): DateTimeImmutable
{
    return new DateTimeImmutable("{$dia} {$hora}:00");
}

/**
 * Franjas de `$duracion` minutos en `$pistas` pistas desde `$desde` hasta `$hasta`.
 *
 * @return list<FranjaDisponible>
 */
function franjas(int $pistas, string $desde, string $hasta, int $duracion = 60): array
{
    $franjas = [];
    $id = 1;
    for ($pista = 1; $pista <= $pistas; $pista++) {
        $inicio = hora($desde);
        while ($inicio->modify("+{$duracion} minutes") <= hora($hasta)) {
            $franjas[] = new FranjaDisponible($id++, $pista, $inicio, $inicio->modify("+{$duracion} minutes"));
            $inicio = $inicio->modify("+{$duracion} minutes");
        }
    }

    return $franjas;
}

/** Un partido de grupo entre dos parejas de dos jugadores: 10a y 10a+1, 10b y 10b+1. */
function partidoDe(int $id, int $a, int $b, int $categoria = 1, ?int $jornada = 1, ?int $ronda = null, array $dependeDe = [], int $duracion = 60): PartidoAPlanificar
{
    return new PartidoAPlanificar($id, $categoria, $categoria, $ronda, $jornada, $a, $b, [10 * $a, 10 * $a + 1, 10 * $b, 10 * $b + 1], $duracion, $dependeDe);
}

function planificar(array $partidos, array $franjas, ?RestriccionesDePlanificacion $restricciones = null)
{
    return (new Planificador)->planificar($partidos, $franjas, $restricciones ?? new RestriccionesDePlanificacion(descansoMinimoMinutos: 30));
}

test('20 partidos de 4 grupos de 4 caben en 4 pistas sin solapar a nadie', function () {
    $partidos = [];
    $id = 1;
    // 4 grupos de 4 parejas: parejas 1-4, 5-8, 9-12, 13-16. Todos contra todos: 6 por grupo = 24.
    // Con 2 partidos por pareja serían 4 por grupo = 16. Usamos 5 por grupo (20): las primeras 5 de cada todos contra todos.
    foreach ([[1, 2, 3, 4], [5, 6, 7, 8], [9, 10, 11, 12], [13, 14, 15, 16]] as $grupo) {
        $cruces = [[0, 1], [2, 3], [0, 2], [1, 3], [0, 3]];
        foreach ($cruces as $jornada => [$i, $j]) {
            $partidos[] = partidoDe($id++, $grupo[$i], $grupo[$j], 1, intdiv($jornada, 2) + 1);
        }
    }

    $planificacion = planificar($partidos, franjas(4, '09:00', '21:00'));

    expect($planificacion->asignaciones)->toHaveCount(20)
        ->and($planificacion->conflictos)->toBe([]);

    // Nadie juega dos partidos a la vez ni sin 30 minutos entre ellos.
    $porJugador = [];
    foreach ($planificacion->asignaciones as $asignacion) {
        $partido = $partidos[$asignacion->partido - 1];
        foreach ($partido->jugadores as $jugador) {
            foreach ($porJugador[$jugador] ?? [] as $otro) {
                expect($asignacion->franja->intervalo()->solapa($otro))->toBeFalse();
                $hueco = max($asignacion->franja->inicio->getTimestamp() - $otro->hasta->getTimestamp(), $otro->desde->getTimestamp() - $asignacion->franja->fin->getTimestamp());
                expect($hueco)->toBeGreaterThanOrEqual(30 * 60);
            }
            $porJugador[$jugador][] = $asignacion->franja->intervalo();
        }
    }

    // Y la primera franja la ocupa el primer partido.
    expect($planificacion->franjaDe(1)?->inicio->format('H:i'))->toBe('09:00');
});

test('un jugador en dos categorías no se solapa', function () {
    // El jugador 10 (pareja 1) juega también en la pareja 21 de la categoría 2.
    $deCategoria1 = partidoDe(1, 1, 2);
    $deCategoria2 = new PartidoAPlanificar(2, 2, 2, null, 1, 21, 22, [10, 211, 220, 221], 60);

    $planificacion = planificar([$deCategoria1, $deCategoria2], franjas(2, '09:00', '12:00'));

    expect($planificacion->conflictos)->toBe([])
        ->and($planificacion->franjaDe(1)?->inicio->format('H:i'))->toBe('09:00')
        // En la pista 2 a las 9:00 se solaparía; a las 10:00 no hay descanso; a las 10:30 tampoco cabe (franjas de 60 desde 9): 11:00.
        ->and($planificacion->franjaDe(2)?->inicio->format('H:i'))->toBe('11:00');
});

test('el descanso mínimo se respeta', function () {
    $partidos = [partidoDe(1, 1, 2), partidoDe(2, 1, 3)];

    $sinDescanso = planificar($partidos, franjas(1, '09:00', '12:00'), new RestriccionesDePlanificacion(descansoMinimoMinutos: 0));
    $conDescanso = planificar($partidos, franjas(1, '09:00', '12:00'), new RestriccionesDePlanificacion(descansoMinimoMinutos: 30));

    expect($sinDescanso->franjaDe(2)?->inicio->format('H:i'))->toBe('10:00')
        ->and($conDescanso->franjaDe(2)?->inicio->format('H:i'))->toBe('11:00');
});

test('una no disponibilidad del jugador aparta el partido de esas horas', function () {
    $noPuede = [10 => [new Intervalo(hora('09:00'), hora('11:00'))]];
    $planificacion = planificar([partidoDe(1, 1, 2)], franjas(1, '09:00', '13:00'), new RestriccionesDePlanificacion(30, $noPuede));

    expect($planificacion->franjaDe(1)?->inicio->format('H:i'))->toBe('11:00');
});

test('la segunda ronda va después de la primera', function () {
    $semifinal1 = partidoDe(1, 1, 2, 1, null, 1);
    $semifinal2 = partidoDe(2, 3, 4, 1, null, 1);
    $final = new PartidoAPlanificar(3, 1, 1, 2, null, null, null, [], 60, [1, 2]);

    $planificacion = planificar([$final, $semifinal1, $semifinal2], franjas(2, '09:00', '13:00'));

    expect($planificacion->conflictos)->toBe([])
        ->and($planificacion->franjaDe(1)?->inicio->format('H:i'))->toBe('09:00')
        ->and($planificacion->franjaDe(2)?->inicio->format('H:i'))->toBe('09:00')
        ->and($planificacion->franjaDe(3)?->inicio->format('H:i'))->toBe('10:00');
});

test('sin franjas suficientes devuelve conflictos con el motivo más cercano y no lanza', function () {
    // Tres pistas a las 9: el tercer partido tiene franja, pero la pareja 1 ya juega a esa hora.
    $partidos = [partidoDe(1, 1, 2), partidoDe(2, 3, 4), partidoDe(3, 1, 3)];
    $planificacion = planificar($partidos, franjas(3, '09:00', '10:00'));

    expect($planificacion->asignaciones)->toHaveCount(2)
        ->and($planificacion->conflictos)->toHaveCount(1)
        ->and($planificacion->conflictos[0]->partido)->toBe(3)
        ->and($planificacion->conflictos[0]->tipo)->toBe(Conflicto::JUGADOR_SOLAPADO);

    $sinNada = planificar([partidoDe(1, 1, 2)], []);

    expect($sinNada->conflictos[0]->tipo)->toBe(Conflicto::SIN_FRANJA);

    $sinDependencia = planificar([new PartidoAPlanificar(9, 1, 1, 2, null, null, null, [], 60, [99])], franjas(1, '09:00', '10:00'));

    expect($sinDependencia->conflictos[0]->tipo)->toBe(Conflicto::ORDEN_DE_RONDAS);
});

test('los partidos ya fijados se respetan y ocupan a sus jugadores', function () {
    $franjas = franjas(1, '09:00', '12:00');
    $fijado = partidoDe(1, 1, 2);
    $nuevo = partidoDe(2, 1, 3);

    $planificacion = planificar([$fijado, $nuevo], $franjas, new RestriccionesDePlanificacion(30, [], [1 => $franjas[0]]));

    expect($planificacion->asignaciones)->toHaveCount(1)
        ->and($planificacion->asignaciones[0])->toBeInstanceOf(Asignacion::class)
        ->and($planificacion->asignaciones[0]->partido)->toBe(2)
        // La franja de las 9 está ocupada por el fijado; a las 10 la pareja 1 no tendría descanso; a las 11 sí.
        ->and($planificacion->franjaDe(2)?->inicio->format('H:i'))->toBe('11:00');
});

test('un partido más largo que la franja no cabe en ella', function () {
    $planificacion = planificar([partidoDe(1, 1, 2, duracion: 90)], franjas(1, '09:00', '11:00'));

    expect($planificacion->conflictos[0]->tipo)->toBe(Conflicto::SIN_FRANJA);

    $larga = [new FranjaDisponible(1, 1, hora('09:00'), hora('10:30'))];

    expect(planificar([partidoDe(1, 1, 2, duracion: 90)], $larga)->asignaciones)->toHaveCount(1);
});

test('la prioridad de categoría manda sobre el orden de llegada', function () {
    $segunda = partidoDe(1, 1, 2, 2);
    $primera = partidoDe(2, 3, 4, 1);

    $planificacion = planificar([$segunda, $primera], franjas(1, '09:00', '11:00'));

    expect($planificacion->franjaDe(2)?->inicio->format('H:i'))->toBe('09:00')
        ->and($planificacion->franjaDe(1)?->inicio->format('H:i'))->toBe('10:00');
});
