<?php

use App\Competicion\Datos\Grupo;
use App\Competicion\Datos\Partido;
use App\Competicion\Errores\NumeroDePartidosNoAlcanzable;
use App\Competicion\GeneradorRoundRobin;

function grupoDe(int $n): Grupo
{
    return new Grupo('A', 1, range(1, $n), 50);
}

/** @param  list<Partido>  $partidos */
function partidosPorParticipante(array $partidos): array
{
    $cuenta = [];
    foreach ($partidos as $p) {
        $cuenta[$p->local] = ($cuenta[$p->local] ?? 0) + 1;
        $cuenta[$p->visitante] = ($cuenta[$p->visitante] ?? 0) + 1;
    }
    ksort($cuenta);

    return $cuenta;
}

/** @param  list<Partido>  $partidos */
function crucesUnicos(array $partidos): bool
{
    $vistos = [];
    foreach ($partidos as $p) {
        $clave = min($p->local, $p->visitante).'-'.max($p->local, $p->visitante);
        if (isset($vistos[$clave])) {
            return false;
        }
        $vistos[$clave] = true;
    }

    return true;
}

test('todos contra todos con 3, 4 y 5 participantes', function (int $n, int $partidos, int $jornadas) {
    $generados = (new GeneradorRoundRobin)->generar(grupoDe($n));

    expect($generados)->toHaveCount($partidos)
        ->and(crucesUnicos($generados))->toBeTrue()
        ->and(array_values(array_unique(partidosPorParticipante($generados))))->toBe([$n - 1])
        ->and(max(array_map(fn (Partido $p): int => $p->jornada, $generados)))->toBe($jornadas)
        ->and($generados[0]->grupo)->toBe(50);
})->with([
    'tres' => [3, 3, 3],
    'cuatro' => [4, 6, 3],
    'cinco' => [5, 10, 5],
]);

test('en cada jornada nadie juega dos veces', function () {
    $generados = (new GeneradorRoundRobin)->generar(grupoDe(6));

    foreach ([1, 2, 3, 4, 5] as $jornada) {
        $deLaJornada = array_filter($generados, fn (Partido $p): bool => $p->jornada === $jornada);
        $ids = array_merge(...array_map(fn (Partido $p): array => [$p->local, $p->visitante], array_values($deLaJornada)));

        expect($ids)->toHaveCount(6)->and(array_unique($ids))->toHaveCount(6);
    }
});

test('en un grupo impar alguien descansa cada jornada, y cada uno descansa una vez', function () {
    $descansos = (new GeneradorRoundRobin)->descansos(grupoDe(5));

    expect($descansos)->toHaveCount(5)
        ->and(array_values($descansos))->toEqualCanonicalizing([1, 2, 3, 4, 5])
        ->and((new GeneradorRoundRobin)->descansos(grupoDe(4)))->toBe([]);
});

test('4 participantes con 2 partidos por pareja dan 4 partidos y cada uno juega 2', function () {
    $generados = (new GeneradorRoundRobin)->generar(grupoDe(4), 2);

    expect($generados)->toHaveCount(4)
        ->and(crucesUnicos($generados))->toBeTrue()
        ->and(array_values(array_unique(partidosPorParticipante($generados))))->toBe([2]);
});

test('4 participantes con 3 por pareja es todos contra todos', function () {
    expect((new GeneradorRoundRobin)->generar(grupoDe(4), 3))->toHaveCount(6);
});

test('5 participantes con 2 por pareja dan 5 partidos sin repetir', function () {
    $generados = (new GeneradorRoundRobin)->generar(grupoDe(5), 2);

    expect($generados)->toHaveCount(5)
        ->and(crucesUnicos($generados))->toBeTrue()
        ->and(array_values(array_unique(partidosPorParticipante($generados))))->toBe([2]);
});

test('un reparto imposible lanza: impar por impar, o más partidos que rivales', function () {
    expect(fn () => (new GeneradorRoundRobin)->generar(grupoDe(5), 1))->toThrow(NumeroDePartidosNoAlcanzable::class)
        ->and(fn () => (new GeneradorRoundRobin)->generar(grupoDe(5), 3))->toThrow(NumeroDePartidosNoAlcanzable::class)
        ->and(fn () => (new GeneradorRoundRobin)->generar(grupoDe(4), 4))->toThrow(NumeroDePartidosNoAlcanzable::class);
});

test('ida y vuelta duplica los partidos invirtiendo local y visitante', function () {
    $generados = (new GeneradorRoundRobin)->generar(grupoDe(4), null, true);

    expect($generados)->toHaveCount(12);

    $ida = array_slice($generados, 0, 6);
    $vuelta = array_slice($generados, 6);

    foreach ($ida as $i => $partido) {
        expect($vuelta[$i]->local)->toBe($partido->visitante)
            ->and($vuelta[$i]->visitante)->toBe($partido->local)
            ->and($vuelta[$i]->jornada)->toBe($partido->jornada + 3);
    }
});

test('nadie es siempre visitante en un grupo de 4', function () {
    $generados = (new GeneradorRoundRobin)->generar(grupoDe(4));
    $locales = array_count_values(array_map(fn (Partido $p): int => $p->local, $generados));

    expect(array_keys($locales))->toHaveCount(4);
});

test('un grupo de 1 no genera partidos', function () {
    expect((new GeneradorRoundRobin)->generar(grupoDe(1)))->toBe([]);
});
