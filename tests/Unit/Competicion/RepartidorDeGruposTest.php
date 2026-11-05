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

test('las parejas fijadas a mano van a su grupo y el resto se sortea en los huecos', function () {
    $reparto = repartir(participantes(12, 3), ['numero_de_grupos' => 3, 'modo_de_sorteo' => 'cabezas_de_serie', 'grupos_manuales' => [[10, 11], [], [12, 1]]]);

    expect($reparto->grupos[0]->participantes)->toContain(10, 11)
        ->and($reparto->grupos[2]->participantes)->toContain(12, 1)
        ->and(array_map(fn (Grupo $g): int => $g->tamano(), $reparto->grupos))->toBe([4, 4, 4])
        ->and(array_merge(...array_map(fn (Grupo $g): array => $g->participantes, $reparto->grupos)))->toEqualCanonicalizing(range(1, 12));

    // Las cabezas que quedan sin fijar (2 y 3) siguen repartiéndose una por grupo.
    $cabezasPorGrupo = array_map(fn (Grupo $g): int => count(array_intersect($g->participantes, [1, 2, 3])), $reparto->grupos);
    expect($cabezasPorGrupo)->toBe([1, 1, 1]);
});

test('fijar más parejas de las que caben, en un grupo que no existe o dos veces la misma lanza', function () {
    expect(fn () => repartir(participantes(8), ['numero_de_grupos' => 2, 'grupos_manuales' => [[1, 2, 3, 4, 5]]]))->toThrow(ConfiguracionInvalida::class, 'caben 4')
        ->and(fn () => repartir(participantes(8), ['numero_de_grupos' => 2, 'grupos_manuales' => [[], [], [1]]]))->toThrow(ConfiguracionInvalida::class, 'no hay grupo C')
        ->and(fn () => repartir(participantes(8), ['numero_de_grupos' => 2, 'grupos_manuales' => [[1], [1]]]))->toThrow(ParticipanteDuplicado::class)
        ->and(fn () => repartir(participantes(8), ['numero_de_grupos' => 2, 'grupos_manuales' => [[99]]]))->toThrow(ConfiguracionInvalida::class, 'no está');
});

test('las parejas a repartir caen una por grupo y, si hay más que grupos, equilibradas', function () {
    $porGrupo = fn ($reparto, array $ids): array => array_map(fn (Grupo $g): int => count(array_intersect($g->participantes, $ids)), $reparto->grupos);

    expect($porGrupo(repartir(participantes(12), ['numero_de_grupos' => 3, 'repartidos' => [10, 11, 12]]), [10, 11, 12]))->toBe([1, 1, 1])
        ->and($porGrupo(repartir(participantes(12), ['numero_de_grupos' => 3, 'repartidos' => [1, 2, 3, 4, 5, 6]]), [1, 2, 3, 4, 5, 6]))->toBe([2, 2, 2]);

    // Con cabezas de serie: una cabeza y una repartida en cada grupo.
    $reparto = repartir(participantes(12, 3), ['numero_de_grupos' => 3, 'modo_de_sorteo' => 'cabezas_de_serie', 'repartidos' => [4, 5, 6]]);
    expect($porGrupo($reparto, [1, 2, 3]))->toBe([1, 1, 1])
        ->and($porGrupo($reparto, [4, 5, 6]))->toBe([1, 1, 1])
        ->and(array_merge(...array_map(fn (Grupo $g): array => $g->participantes, $reparto->grupos)))->toEqualCanonicalizing(range(1, 12));
});

test('a qué grupo cae cada repartida lo decide el azar, y con la misma semilla se repite', function () {
    $grupoDe10 = fn (int $semilla): string => collect(repartir(participantes(12), ['numero_de_grupos' => 3, 'repartidos' => [10, 11, 12], 'semilla' => $semilla])->grupos)
        ->first(fn (Grupo $g): bool => in_array(10, $g->participantes, true))->nombre;

    expect($grupoDe10(5))->toBe($grupoDe10(5))
        ->and(collect(range(1, 20))->map($grupoDe10)->unique()->count())->toBeGreaterThan(1);
});

test('una pareja a repartir que no está en la fase lanza', function () {
    expect(fn () => repartir(participantes(8), ['numero_de_grupos' => 2, 'repartidos' => [99]]))->toThrow(ConfiguracionInvalida::class, 'no está');
});

test('por tandas: cada tanda cae una por grupo sin tocar lo ya colocado ni sortear a nadie más', function () {
    $repartidor = new RepartidorDeGrupos;
    $config = fn (array $fijos, array $tanda): ConfiguracionDeGrupos => ConfiguracionDeGrupos::desdeArray(['numero_de_grupos' => 4, 'grupos_manuales' => $fijos, 'repartidos' => $tanda]);
    $deTanda = fn ($reparto, array $tanda): array => array_map(fn (Grupo $g): int => count(array_intersect($g->participantes, $tanda)), $reparto->grupos);

    // Cuatro tandas de cuatro parejas en cuatro grupos: cada grupo, una de cada tanda.
    $fijos = [];
    foreach (array_chunk(range(1, 16), 4) as $tanda) {
        $reparto = $repartidor->colocarTanda(participantes(16), $config($fijos, $tanda));
        expect($deTanda($reparto, $tanda))->toBe([1, 1, 1, 1]);

        foreach ($fijos as $i => $miembros) {
            expect(array_slice($reparto->grupos[$i]->participantes, 0, count($miembros)))->toBe($miembros);
        }
        $fijos = array_map(fn (Grupo $g): array => $g->participantes, $reparto->grupos);
    }

    expect(array_map('count', $fijos))->toBe([4, 4, 4, 4])
        ->and(array_merge(...$fijos))->toEqualCanonicalizing(range(1, 16));

    // Una tanda sola no coloca a nadie más.
    $solo = $repartidor->colocarTanda(participantes(16), $config([], [1, 2]));
    expect(count(array_merge(...array_map(fn (Grupo $g): array => $g->participantes, $solo->grupos))))->toBe(2);
});

test('por tandas se respeta un grupo llenado de más a mano y la tanda va a los que tienen hueco', function () {
    $reparto = (new RepartidorDeGrupos)->colocarTanda(participantes(12), ConfiguracionDeGrupos::desdeArray(['numero_de_grupos' => 3, 'grupos_manuales' => [[1, 2, 3, 4, 5]], 'repartidos' => [6, 7]]));

    expect($reparto->grupos[0]->participantes)->toBe([1, 2, 3, 4, 5])
        ->and(count(array_intersect($reparto->grupos[1]->participantes, [6, 7])))->toBe(1)
        ->and(count(array_intersect($reparto->grupos[2]->participantes, [6, 7])))->toBe(1);
});

/** Parejas 1…n con nivel igual a su id (la 12 es la de más nivel). */
function conNiveles(int $n): array
{
    return array_map(fn (int $i): Participante => new Participante($i, "Pareja {$i}", null, [], (float) $i), range(1, $n));
}

test('por niveles: bombos de más a menos nivel, una pareja de cada bombo en cada grupo', function () {
    $reparto = repartir(conNiveles(12), ['numero_de_grupos' => 3, 'modo_de_sorteo' => 'por_niveles']);

    foreach ([[12, 11, 10], [9, 8, 7], [6, 5, 4], [3, 2, 1]] as $bombo) {
        expect(array_map(fn (Grupo $g): int => count(array_intersect($g->participantes, $bombo)), $reparto->grupos))->toBe([1, 1, 1]);
    }
    expect(array_merge(...array_map(fn (Grupo $g): array => $g->participantes, $reparto->grupos)))->toEqualCanonicalizing(range(1, 12));
});

test('por niveles: dentro de cada bombo decide el azar, y la misma semilla repite el sorteo', function () {
    $grupoDe12 = fn (int $semilla): string => collect(repartir(conNiveles(12), ['numero_de_grupos' => 3, 'modo_de_sorteo' => 'por_niveles', 'semilla' => $semilla])->grupos)
        ->first(fn (Grupo $g): bool => in_array(12, $g->participantes, true))->nombre;

    expect($grupoDe12(9))->toBe($grupoDe12(9))
        ->and(collect(range(1, 20))->map($grupoDe12)->unique()->count())->toBeGreaterThan(1);
});

test('por niveles: las parejas sin nivel van al último bombo y lo fijado a mano se respeta', function () {
    $parejas = [...conNiveles(9), new Participante(10, 'Sin nivel', null, [], null), new Participante(11, 'Sin nivel 2', null, [], null), new Participante(12, 'Sin nivel 3', null, [], null)];
    $reparto = repartir($parejas, ['numero_de_grupos' => 3, 'modo_de_sorteo' => 'por_niveles']);
    expect(array_map(fn (Grupo $g): int => count(array_intersect($g->participantes, [10, 11, 12])), $reparto->grupos))->toBe([1, 1, 1]);

    $conFija = repartir($parejas, ['numero_de_grupos' => 3, 'modo_de_sorteo' => 'por_niveles', 'grupos_manuales' => [[1]]]);
    expect($conFija->grupos[0]->participantes)->toContain(1)
        ->and(array_map(fn (Grupo $g): int => count(array_intersect($g->participantes, [9, 8, 7])), $conFija->grupos))->toBe([1, 1, 1])
        ->and(array_map(fn (Grupo $g): int => $g->tamano(), $conFija->grupos))->toBe([4, 4, 4]);
});
