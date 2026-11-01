<?php

use App\Competicion\ConstructorDeCuadro;
use App\Competicion\Datos\Clasificado;
use App\Competicion\Datos\ConfiguracionDeEliminatoria;
use App\Competicion\Datos\Cuadro;
use App\Competicion\Datos\Partido;
use App\Competicion\Errores\ConfiguracionInvalida;
use App\Competicion\Errores\CuadroDemasiadoPequeno;
use App\Competicion\Errores\ParticipanteDuplicado;
use App\Enums\EstadoPartido;
use App\Enums\TipoDeOrigen;

/**
 * Clasificados de `g` grupos con `porGrupo` por grupo, en orden global:
 * primeros (ids 11, 21, 31…), luego segundos (12, 22…).
 *
 * @return list<Clasificado>
 */
function clasificadosDe(int $grupos, int $porGrupo): array
{
    $lista = [];
    $orden = 1;
    for ($posicion = 1; $posicion <= $porGrupo; $posicion++) {
        for ($grupo = 1; $grupo <= $grupos; $grupo++) {
            $lista[] = new Clasificado($grupo * 10 + $posicion, $grupo, $posicion, "{$posicion}º G{$grupo}", $orden++);
        }
    }

    return $lista;
}

function construir(array $clasificados, array $configuracion = []): Cuadro
{
    return (new ConstructorDeCuadro)->construir($clasificados, ConfiguracionDeEliminatoria::desdeArray($configuracion));
}

/** Los cruces de la primera ronda como pares [local, visitante]. */
function primeraRonda(Cuadro $cuadro): array
{
    return array_map(fn (Partido $p): array => [$p->local, $p->visitante], $cuadro->partidosDeRonda(1));
}

test('8 clasificados exactos dan un cuadro de 8 con 7 partidos y 3 rondas', function () {
    $cuadro = construir(clasificadosDe(4, 2));

    expect($cuadro->tamano)->toBe(8)
        ->and($cuadro->rondas)->toBe(3)
        ->and($cuadro->partidos)->toHaveCount(7)
        ->and($cuadro->partidosDeRonda(1))->toHaveCount(4)
        ->and($cuadro->partidosDeRonda(2))->toHaveCount(2)
        ->and($cuadro->partidosDeRonda(3))->toHaveCount(1)
        ->and($cuadro->avisos)->toBe([]);

    $final = $cuadro->final();

    expect($final->origenLocal?->tipo)->toBe(TipoDeOrigen::GanadorDe)
        ->and($final->origenLocal?->referencia)->toBe(5)
        ->and($final->origenVisitante?->referencia)->toBe(6)
        ->and($final->local)->toBeNull();
});

test('las cabezas 1 y 2 quedan en mitades opuestas, y 1º del A juega contra un 2º', function () {
    $cuadro = construir(clasificadosDe(4, 2));
    $cruces = primeraRonda($cuadro);

    // Siembra de 8: [1, 8, 4, 5, 2, 7, 3, 6] → 11-42, 41-12, 21-32, 31-22.
    expect($cruces[0][0])->toBe(11)
        ->and($cruces[2][0])->toBe(21)
        ->and($cruces)->toBe([[11, 42], [41, 12], [21, 32], [31, 22]])
        ->and(array_map(fn (array $c): int => intdiv($c[1], 10) === 0 ? 0 : $c[1] % 10, $cruces))->toBe([2, 2, 2, 2]);
});

test('12 clasificados caben en un cuadro de 16 con 4 byes para los 4 mejores', function () {
    $cuadro = construir(clasificadosDe(4, 3));

    expect($cuadro->tamano)->toBe(16)->and($cuadro->rondas)->toBe(4);

    $conBye = array_values(array_filter($cuadro->partidosDeRonda(1), fn (Partido $p): bool => $p->local === null || $p->visitante === null));

    expect($conBye)->toHaveCount(4)
        ->and(array_map(fn (Partido $p): int => $p->local ?? $p->visitante, $conBye))->toEqualCanonicalizing([11, 21, 31, 41])
        ->and(array_map(fn (Partido $p): EstadoPartido => $p->estado, $conBye))->toBe(array_fill(0, 4, EstadoPartido::Walkover))
        ->and(array_map(fn (Partido $p): int => $p->ganador, $conBye))->toEqualCanonicalizing([11, 21, 31, 41]);

    // Los cuatro mejores ya están en la segunda ronda.
    $segunda = $cuadro->partidosDeRonda(2);
    $yaColocados = array_filter(array_merge(...array_map(fn (Partido $p): array => [$p->local, $p->visitante], $segunda)));

    expect(array_values($yaColocados))->toEqualCanonicalizing([11, 21, 31, 41]);
});

test('entre grupos se evitan los reencuentros de la primera ronda', function () {
    // Con 2 grupos y 2 por grupo la siembra directa cruza 11-22 y 21-12: sin reencuentro.
    // Con 2 grupos y 4 por grupo, la siembra directa haría 11 (1º A) contra 24... (4º B): bien; pero
    // forzamos un caso: 3 grupos con 1º y 2º y un 3º del mismo grupo que el 1º.
    $clasificados = [
        new Clasificado(11, 1, 1, '1º A', 1),
        new Clasificado(21, 2, 1, '1º B', 2),
        new Clasificado(12, 1, 2, '2º A', 3),
        new Clasificado(22, 2, 2, '2º B', 4),
    ];

    // Siembra directa: huecos [1, 4, 2, 3] → 11-22 y 21-12. Sin reencuentros ya.
    $cuadro = construir($clasificados);

    expect(primeraRonda($cuadro))->toBe([[11, 22], [21, 12]]);

    // Ahora un caso en el que la siembra directa sí repite: el orden global
    // pone a 12 (2º A) como cuarto, enfrente de 11 (1º A).
    $forzado = [
        new Clasificado(11, 1, 1, '1º A', 1),
        new Clasificado(21, 2, 1, '1º B', 2),
        new Clasificado(22, 2, 2, '2º B', 3),
        new Clasificado(12, 1, 2, '2º A', 4),
    ];

    $cuadro = construir($forzado);

    foreach (primeraRonda($cuadro) as [$local, $visitante]) {
        expect(intdiv($local, 10))->not->toBe(intdiv($visitante, 10));
    }

    expect($cuadro->avisos)->toBe([]);

    $sinEvitar = construir($forzado, ['evitar_reencuentros' => false]);

    expect(primeraRonda($sinEvitar))->toBe([[11, 12], [21, 22]]);
});

test('cuando no hay forma de evitar un reencuentro, se avisa', function () {
    $clasificados = [
        new Clasificado(11, 1, 1, '1º A', 1),
        new Clasificado(12, 1, 2, '2º A', 2),
    ];

    $cuadro = construir($clasificados);

    expect($cuadro->tamano)->toBe(2)
        ->and($cuadro->avisos[0])->toContain('reencuentro')
        ->and(primeraRonda($cuadro))->toBe([[11, 12]]);
});

test('el sorteo con semilla se repite y respeta los byes a los mejores', function () {
    $clasificados = clasificadosDe(3, 2);

    $a = construir($clasificados, ['regla_de_cruces' => 'sorteo', 'semilla' => 5]);
    $b = construir($clasificados, ['regla_de_cruces' => 'sorteo', 'semilla' => 5]);
    $c = construir($clasificados, ['regla_de_cruces' => 'sorteo', 'semilla' => 6]);

    expect(primeraRonda($a))->toBe(primeraRonda($b))
        ->and(primeraRonda($a))->not->toBe(primeraRonda($c))
        ->and($a->tamano)->toBe(8);

    $conBye = array_values(array_filter($a->partidosDeRonda(1), fn (Partido $p): bool => $p->local === null || $p->visitante === null));

    expect(array_map(fn (Partido $p): int => $p->local ?? $p->visitante, $conBye))->toEqualCanonicalizing([11, 21]);
});

test('el partido por el tercer puesto enfrenta a los perdedores de las semifinales', function () {
    $cuadro = construir(clasificadosDe(4, 2), ['tercer_y_cuarto_puesto' => true]);
    $tercero = $cuadro->tercerPuesto();

    expect($cuadro->partidos)->toHaveCount(8)
        ->and($tercero)->not->toBeNull()
        ->and($tercero->ronda)->toBe(3)
        ->and($tercero->posicion)->toBe(2)
        ->and($tercero->origenLocal?->tipo)->toBe(TipoDeOrigen::PerdedorDe)
        ->and($tercero->origenLocal?->referencia)->toBe(5)
        ->and($tercero->origenVisitante?->referencia)->toBe(6)
        ->and($cuadro->partidosDeRonda(3))->toHaveCount(1);
});

test('los cruces manuales se respetan y se validan', function () {
    $clasificados = clasificadosDe(2, 2);

    $cuadro = construir($clasificados, ['regla_de_cruces' => 'manual', 'cruces_manuales' => [[11, 12], [21, 22]]]);

    expect(primeraRonda($cuadro))->toBe([[11, 12], [21, 22]]);

    $conBye = construir($clasificados, ['regla_de_cruces' => 'manual', 'tamano_de_cuadro' => 8, 'cruces_manuales' => [[11, null], [12, null], [21, null], [22, null]]]);

    expect($conBye->tamano)->toBe(8)
        ->and(array_filter(array_map(fn (Partido $p): ?int => $p->ganador, $conBye->partidosDeRonda(1))))->toHaveCount(4);

    expect(fn () => construir($clasificados, ['regla_de_cruces' => 'manual', 'cruces_manuales' => [[11, 12]]]))->toThrow(ConfiguracionInvalida::class, 'cruces')
        ->and(fn () => construir($clasificados, ['regla_de_cruces' => 'manual', 'cruces_manuales' => [[11, 12], [21, 99]]]))->toThrow(ConfiguracionInvalida::class, 'no está')
        ->and(fn () => construir($clasificados, ['regla_de_cruces' => 'manual', 'cruces_manuales' => [[11, 12], [21, 11]]]))->toThrow(ParticipanteDuplicado::class)
        ->and(fn () => construir($clasificados, ['regla_de_cruces' => 'manual', 'cruces_manuales' => [[11, 12], [21, null]]]))->toThrow(ConfiguracionInvalida::class, 'Faltan');
});

test('un cuadro demasiado pequeño o con menos de 2 clasificados lanza', function () {
    expect(fn () => construir(clasificadosDe(4, 2), ['tamano_de_cuadro' => 4]))->toThrow(CuadroDemasiadoPequeno::class)
        ->and(fn () => construir([new Clasificado(1, 1, 1, '1º A', 1)]))->toThrow(ConfiguracionInvalida::class)
        ->and(fn () => construir([new Clasificado(1, 1, 1, '1º A', 1), new Clasificado(1, 2, 1, '1º B', 2)]))->toThrow(ParticipanteDuplicado::class);
});

test('el orden de siembra estándar pone a 1 y 2 en extremos y a 3 y 4 en los cuartos restantes', function () {
    expect(ConstructorDeCuadro::ordenDeSiembra(8))->toBe([1, 8, 4, 5, 2, 7, 3, 6])
        ->and(ConstructorDeCuadro::ordenDeSiembra(4))->toBe([1, 4, 2, 3])
        ->and(ConstructorDeCuadro::potenciaDeDos(12))->toBe(16)
        ->and(ConstructorDeCuadro::potenciaDeDos(16))->toBe(16)
        ->and(ConstructorDeCuadro::potenciaDeDos(1))->toBe(2);
});
