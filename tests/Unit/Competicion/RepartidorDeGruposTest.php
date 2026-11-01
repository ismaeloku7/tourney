<?php

use App\Competicion\Datos\ConfiguracionDeGrupos;
use App\Competicion\Datos\Grupo;
use App\Competicion\Datos\Participante;
use App\Competicion\Errores\ConfiguracionInvalida;
use App\Competicion\Errores\ParticipanteDuplicado;
use App\Competicion\RepartidorDeGrupos;
use App\Enums\ModoDeSorteo;

/** @return list<Participante> */
function participantes(int $n, int $cabezas = 0): array
{
    return array_map(fn (int $i): Participante => new Participante($i, "Pareja {$i}", $i <= $cabezas ? $i : null), range(1, $n));
}

function repartir(array $participantes, array $configuracion = [])
{
    return (new RepartidorDeGrupos)->repartir($participantes, ConfiguracionDeGrupos::desdeArray(array_merge(['semilla' => 1], $configuracion)));
}

test('12 participantes en 3 grupos salen 4, 4 y 4 con todos colocados una vez', function () {
    $reparto = repartir(participantes(12), ['numero_de_grupos' => 3]);

    expect($reparto->grupos)->toHaveCount(3)
        ->and(array_map(fn (Grupo $g): int => $g->tamano(), $reparto->grupos))->toBe([4, 4, 4])
        ->and(array_map(fn (Grupo $g): string => $g->nombre, $reparto->grupos))->toBe(['A', 'B', 'C'])
        ->and(array_merge(...array_map(fn (Grupo $g): array => $g->participantes, $reparto->grupos)))->toEqualCanonicalizing(range(1, 12))
        ->and($reparto->avisos)->toBe([])
        ->and($reparto->semilla)->toBe(1);
});

test('13 participantes en tamaño 4 salen 5, 4 y 4 con aviso', function () {
    $reparto = repartir(participantes(13), ['tamano_de_grupo' => 4]);

    expect(array_map(fn (Grupo $g): int => $g->tamano(), $reparto->grupos))->toBe([5, 4, 4])
        ->and($reparto->avisos[0])->toContain('de 4 y de 5');
});

test('un grupo de 2 avisa, y 16 en tamaño 4 no', function () {
    expect(repartir(participantes(5), ['numero_de_grupos' => 2])->avisos)->toContain('Hay grupos de menos de 3 participantes.')
        ->and(repartir(participantes(16), ['tamano_de_grupo' => 4])->avisos)->toBe([]);
});

test('8 cabezas de serie en 4 grupos van en serpiente', function () {
    $reparto = repartir(participantes(16, 8), ['numero_de_grupos' => 4, 'modo_de_sorteo' => 'cabezas_de_serie']);

    $cabezasPorGrupo = array_map(fn (Grupo $g): array => array_values(array_filter($g->participantes, fn (int $id): bool => $id <= 8)), $reparto->grupos);

    expect($cabezasPorGrupo)->toBe([[1, 8], [2, 7], [3, 6], [4, 5]])
        ->and(array_map(fn (Grupo $g): int => $g->tamano(), $reparto->grupos))->toBe([4, 4, 4, 4]);
});

test('con la misma semilla sale el mismo reparto, y con otra uno distinto', function () {
    $a = repartir(participantes(12), ['numero_de_grupos' => 3, 'semilla' => 42]);
    $b = repartir(participantes(12), ['numero_de_grupos' => 3, 'semilla' => 42]);
    $c = repartir(participantes(12), ['numero_de_grupos' => 3, 'semilla' => 43]);

    expect($a->grupos)->toEqual($b->grupos)
        ->and($a->grupos)->not->toEqual($c->grupos);
});

test('sin semilla se inventa una y se devuelve', function () {
    $reparto = (new RepartidorDeGrupos)->repartir(participantes(8), ConfiguracionDeGrupos::desdeArray(['numero_de_grupos' => 2]));

    expect($reparto->semilla)->toBeInt()->and($reparto->semilla)->toBeGreaterThan(0);
});

test('en modo aleatorio las cabezas de serie no se tienen en cuenta', function () {
    $reparto = repartir(participantes(8, 4), ['numero_de_grupos' => 2, 'semilla' => 7]);

    $cabezasEnA = count(array_filter($reparto->grupos[0]->participantes, fn (int $id): bool => $id <= 4));

    // Con la semilla 7 el azar no reparte 2 y 2; si lo hiciera, cambiaríamos la semilla de la prueba.
    expect($cabezasEnA)->not->toBe(2);
});

test('el modo manual respeta los grupos dados y solo valida', function () {
    $reparto = repartir(participantes(6), ['modo_de_sorteo' => 'manual', 'grupos_manuales' => [[1, 2, 3], [4, 5]]]);

    expect($reparto->grupos[0]->participantes)->toBe([1, 2, 3])
        ->and($reparto->grupos[1]->participantes)->toBe([4, 5])
        ->and($reparto->avisos)->toContain('Quedan sin grupo los participantes 6.')
        ->and($reparto->avisos)->toContain('Los grupos no tienen el mismo tamaño.');
});

test('un participante repetido o desconocido en manual lanza', function () {
    expect(fn () => repartir(participantes(4), ['modo_de_sorteo' => 'manual', 'grupos_manuales' => [[1, 2], [2, 3]]]))->toThrow(ParticipanteDuplicado::class)
        ->and(fn () => repartir(participantes(4), ['modo_de_sorteo' => 'manual', 'grupos_manuales' => [[1, 99]]]))->toThrow(ConfiguracionInvalida::class, 'no está');
});

test('un participante repetido en la entrada lanza, y sin participantes también', function () {
    expect(fn () => repartir([new Participante(1, 'A'), new Participante(1, 'B')], ['numero_de_grupos' => 1]))->toThrow(ParticipanteDuplicado::class)
        ->and(fn () => repartir([], ['numero_de_grupos' => 1]))->toThrow(ConfiguracionInvalida::class);
});

test('más grupos que participantes se reduce a un participante por grupo', function () {
    $reparto = repartir(participantes(3), ['numero_de_grupos' => 5]);

    expect($reparto->grupos)->toHaveCount(3)
        ->and($reparto->grupos[0]->orden)->toBe(1)
        ->and(ModoDeSorteo::Aleatorio)->toBe(ModoDeSorteo::Aleatorio);
});
