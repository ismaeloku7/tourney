<?php

use App\Competicion\Datos\Conflicto;
use App\Competicion\Datos\Intervalo;
use App\Competicion\Datos\OcupacionFija;
use App\Competicion\Datos\PartidoAPlanificar;
use App\Competicion\Datos\PartidoColocado;
use App\Competicion\Datos\PlanDePistas;
use App\Competicion\Datos\RestriccionesDePlanificacion;
use App\Competicion\PlanificadorContinuo;

function a_las(string $hora, string $dia = '2026-11-14'): DateTimeImmutable
{
    return new DateTimeImmutable("{$dia} {$hora}:00");
}

/** @return array<int, list<Intervalo>> */
function pistasDe(int $cuantas, string $desde = '09:00', string $hasta = '21:00'): array
{
    $pistas = [];
    for ($p = 1; $p <= $cuantas; $p++) {
        $pistas[$p] = [new Intervalo(a_las($desde), a_las($hasta))];
    }

    return $pistas;
}

/** Un partido entre las parejas $a y $b, de dos jugadores cada una (10a, 10a+1…). */
function encuentro(int $id, int $a, int $b, int $duracion = 60, ?int $jornada = 1, ?int $ronda = null, array $dependeDe = [], int $categoria = 1): PartidoAPlanificar
{
    return new PartidoAPlanificar($id, $categoria, $categoria, $ronda, $jornada, $a, $b, [10 * $a, 10 * $a + 1, 10 * $b, 10 * $b + 1], $duracion, $dependeDe);
}

/** Los 6 partidos de un grupo de 4 parejas, en 3 jornadas. */
function grupoDeCuatro(int $primerId, int $primeraPareja, int $duracion = 60): array
{
    $p = fn (int $k): int => $primeraPareja + $k;
    $partidos = [];
    foreach ([[[0, 1], [2, 3]], [[0, 2], [1, 3]], [[0, 3], [1, 2]]] as $jornada => $cruces) {
        foreach ($cruces as [$x, $y]) {
            $partidos[] = encuentro($primerId++, $p($x), $p($y), $duracion, $jornada + 1);
        }
    }

    return $partidos;
}

/** Comprueba que nadie juega dos a la vez (ni sin descanso) y que ninguna pista tiene dos partidos a la vez. */
function sinChoques(PlanDePistas $plan, array $partidos, int $descanso): void
{
    $jugadoresDe = [];
    foreach ($partidos as $partido) {
        $jugadoresDe[$partido->id] = $partido->jugadores;
    }

    $porPista = [];
    $porJugador = [];
    foreach ($plan->colocados as $c) {
        $porPista[$c->pista][] = $c;
        foreach ($jugadoresDe[$c->partido] as $jugador) {
            $porJugador[$jugador][] = $c;
        }
    }

    foreach ([[$porPista, 0], [$porJugador, $descanso * 60]] as [$grupos, $margen]) {
        foreach ($grupos as $lista) {
            usort($lista, fn (PartidoColocado $x, PartidoColocado $y): int => $x->inicio <=> $y->inicio);
            for ($i = 1; $i < count($lista); $i++) {
                expect($lista[$i]->inicio->getTimestamp())->toBeGreaterThanOrEqual($lista[$i - 1]->fin->getTimestamp() + $margen);
            }
        }
    }
}

test('cada partido ocupa calentamiento más duración y el siguiente entra en cuanto acaba', function () {
    $partidos = [encuentro(1, 1, 2, 40), encuentro(2, 3, 4, 40), encuentro(3, 5, 6, 75)];

    $plan = (new PlanificadorContinuo)->planificar($partidos, pistasDe(1), new RestriccionesDePlanificacion(30, [], 10));

    expect($plan->conflictos)->toBe([])
        ->and(array_map(fn (PartidoColocado $c): string => $c->inicio->format('H:i').'-'.$c->fin->format('H:i'), $plan->colocados))
        ->toBe(['09:00-09:50', '09:50-10:40', '10:40-12:05'])
        ->and($plan->fin?->format('H:i'))->toBe('12:05')
        ->and($plan->minutosMuertos)->toBe(0)
        ->and($plan->ocupacion)->toBe(100);
});

test('4 grupos de 4 en 4 pistas: todos colocados, sin choques y con descanso', function () {
    $partidos = [...grupoDeCuatro(1, 1), ...grupoDeCuatro(7, 11), ...grupoDeCuatro(13, 21, 40), ...grupoDeCuatro(19, 31, 40)];

    $plan = (new PlanificadorContinuo)->planificar($partidos, pistasDe(4), new RestriccionesDePlanificacion(30, [], 10));

    expect($plan->conflictos)->toBe([])->and($plan->colocados)->toHaveCount(24);
    sinChoques($plan, $partidos, 30);
});

test('con partidos cortos y largos mezclados acaba antes que con huecos fijos del más largo', function () {
    $partidos = [...grupoDeCuatro(1, 1, 40), ...grupoDeCuatro(7, 11, 40), ...grupoDeCuatro(13, 21, 75), ...grupoDeCuatro(19, 31, 75)];

    $plan = (new PlanificadorContinuo)->planificar($partidos, pistasDe(4, '09:00', '23:00'), new RestriccionesDePlanificacion(30, [], 10));

    // Con huecos fijos de 85 minutos (75 + 10), 24 partidos en 4 pistas son 6 huecos por pista: hasta las 17:30.
    expect($plan->conflictos)->toBe([])
        ->and($plan->fin)->toBeLessThan(a_las('17:30'))
        ->and($plan->ocupacion)->toBeGreaterThanOrEqual(85);
    sinChoques($plan, $partidos, 30);
});

test('la ronda siguiente espera a que acaben, con descanso, los partidos que le dan sus parejas', function () {
    $partidos = [
        encuentro(1, 1, 2, 60, null, 1),
        encuentro(2, 3, 4, 60, null, 1),
        new PartidoAPlanificar(3, 1, 1, 2, null, null, null, [], 60, [1, 2]),
    ];

    $plan = (new PlanificadorContinuo)->planificar($partidos, pistasDe(2), new RestriccionesDePlanificacion(30, [], 10));

    expect($plan->conflictos)->toBe([])
        ->and($plan->colocadoDe(1)?->inicio->format('H:i'))->toBe('09:00')
        ->and($plan->colocadoDe(2)?->inicio->format('H:i'))->toBe('09:00')
        // Acaban a las 10:10; más 30 de descanso.
        ->and($plan->colocadoDe(3)?->inicio->format('H:i'))->toBe('10:40');
});

test('respeta las horas en que un jugador no puede, lo ya fijado y los bloqueos', function () {
    $partidos = [encuentro(1, 1, 2), encuentro(2, 3, 4)];
    $fijos = [
        new OcupacionFija(1, new Intervalo(a_las('09:00'), a_las('10:10')), 99, [10]),
        new OcupacionFija(2, new Intervalo(a_las('09:00'), a_las('12:00'))),
    ];

    $plan = (new PlanificadorContinuo)->planificar($partidos, pistasDe(2), new RestriccionesDePlanificacion(30, [30 => [new Intervalo(a_las('09:00'), a_las('13:00'))]], 10), $fijos);

    expect($plan->conflictos)->toBe([])
        // El jugador 10 acaba a las 10:10 en el fijo: 30 de descanso.
        ->and($plan->colocadoDe(1)?->inicio->format('H:i'))->toBe('10:40')
        // El jugador 30 no puede hasta las 13:00.
        ->and($plan->colocadoDe(2)?->inicio->format('H:i'))->toBe('13:00');
});

test('lo que no cabe vuelve como conflicto y lo que depende de ello también', function () {
    $partidos = [
        encuentro(1, 1, 2, 60, null, 1),
        encuentro(2, 3, 4, 60, null, 1),
        new PartidoAPlanificar(3, 1, 1, 2, null, null, null, [], 60, [1, 2]),
    ];

    $plan = (new PlanificadorContinuo)->planificar($partidos, pistasDe(1, '09:00', '11:00'), new RestriccionesDePlanificacion(30, [], 10));

    expect($plan->colocados)->toHaveCount(1)
        ->and(array_map(fn (Conflicto $c): string => $c->tipo, $plan->conflictos))->toBe([Conflicto::SIN_FRANJA, Conflicto::ORDEN_DE_RONDAS]);
});

test('no coloca nada antes de la hora mínima', function () {
    $plan = (new PlanificadorContinuo)->planificar([encuentro(1, 1, 2)], pistasDe(1), new RestriccionesDePlanificacion(30, [], 10, a_las('12:03')));

    expect($plan->colocadoDe(1)?->inicio->format('H:i'))->toBe('12:03');
});
