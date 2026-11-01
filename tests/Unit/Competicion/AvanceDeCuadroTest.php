<?php

use App\Competicion\AvanceDeCuadro;
use App\Competicion\ConstructorDeCuadro;
use App\Competicion\Datos\Clasificado;
use App\Competicion\Datos\ConfiguracionDeEliminatoria;
use App\Competicion\Datos\Cuadro;
use App\Competicion\Datos\Resultado;
use App\Competicion\Datos\Set;
use App\Competicion\Errores\AvanceNoReversible;
use App\Enums\EstadoPartido;

function cuadroDe(int $n, array $configuracion = []): Cuadro
{
    $clasificados = [];
    for ($i = 1; $i <= $n; $i++) {
        $clasificados[] = new Clasificado($i, null, 1, "C{$i}", $i);
    }

    return (new ConstructorDeCuadro)->construir($clasificados, ConfiguracionDeEliminatoria::desdeArray($configuracion));
}

function resultado(): Resultado
{
    return new Resultado([new Set(6, 3), new Set(6, 4)]);
}

test('avanzar lleva al ganador a la siguiente ronda y al perdedor al tercer puesto', function () {
    $cuadro = cuadroDe(4, ['tercer_y_cuarto_puesto' => true]);
    $avance = new AvanceDeCuadro;

    // Semifinales: 1 vs 4 (partido 1) y 2 vs 3 (partido 2).
    $semifinal = $cuadro->partido(1)->conGanador(1, resultado());
    $cuadro = $avance->avanzar($cuadro, $semifinal);

    expect($cuadro->final()->local)->toBe(1)
        ->and($cuadro->final()->visitante)->toBeNull()
        ->and($cuadro->tercerPuesto()->local)->toBe(4)
        ->and($cuadro->partido(1)->estado)->toBe(EstadoPartido::Jugado);

    $cuadro = $avance->avanzar($cuadro, $cuadro->partido(2)->conGanador(3, resultado()));

    expect($cuadro->final()->visitante)->toBe(3)
        ->and($cuadro->tercerPuesto()->visitante)->toBe(2);
});

test('avanzar dos veces el mismo partido no cambia nada', function () {
    $cuadro = cuadroDe(4);
    $avance = new AvanceDeCuadro;
    $cerrado = $cuadro->partido(1)->conGanador(1, resultado());

    $una = $avance->avanzar($cuadro, $cerrado);
    $dos = $avance->avanzar($una, $cerrado);

    expect($dos)->toEqual($una);
});

test('un bye cierra el siguiente partido solo cuando el otro hueco también es bye', function () {
    // Cuadro de 8 con 5 clasificados: tres byes. Cabezas 1, 2 y 3 tienen bye.
    $cuadro = cuadroDe(5);
    $segunda = $cuadro->partidosDeRonda(2);

    // Huecos de 8: [1, bye, 4, 5, 2, bye, 3, bye]. Partidos: 1 vs bye, 4 vs 5, 2 vs bye, 3 vs bye.
    // Segunda ronda: (1 vs ganador 4-5) y (2 vs 3): el segundo ya tiene a los dos.
    expect($segunda[0]->local)->toBe(1)
        ->and($segunda[0]->visitante)->toBeNull()
        ->and($segunda[0]->estado)->toBe(EstadoPartido::Pendiente)
        ->and($segunda[1]->local)->toBe(2)
        ->and($segunda[1]->visitante)->toBe(3)
        ->and($segunda[1]->estado)->toBe(EstadoPartido::Pendiente);

    // Un cuadro de 4 con solo 2 clasificados: los dos byes de la primera
    // ronda llevan a la final directamente.
    $directo = cuadroDe(2, ['tamano_de_cuadro' => 4]);

    expect($directo->final()->local)->toBe(1)
        ->and($directo->final()->visitante)->toBe(2)
        ->and($directo->final()->estado)->toBe(EstadoPartido::Pendiente);
});

test('revertir vacía el hueco siguiente y deja el partido sin resultado', function () {
    $cuadro = cuadroDe(4);
    $avance = new AvanceDeCuadro;

    $cuadro = $avance->avanzar($cuadro, $cuadro->partido(1)->conGanador(1, resultado()));
    expect($cuadro->final()->local)->toBe(1);

    $cuadro = $avance->revertir($cuadro, $cuadro->partido(1));

    expect($cuadro->final()->local)->toBeNull()
        ->and($cuadro->partido(1)->ganador)->toBeNull()
        ->and($cuadro->partido(1)->estado)->toBe(EstadoPartido::Pendiente)
        ->and($cuadro->partido(1)->local)->toBe(1);
});

test('revertir con el siguiente ya jugado lanza', function () {
    $cuadro = cuadroDe(4);
    $avance = new AvanceDeCuadro;

    $cuadro = $avance->avanzar($cuadro, $cuadro->partido(1)->conGanador(1, resultado()));
    $cuadro = $avance->avanzar($cuadro, $cuadro->partido(2)->conGanador(2, resultado()));
    $cuadro = $avance->avanzar($cuadro, $cuadro->final()->conGanador(1, resultado()));

    expect(fn () => $avance->revertir($cuadro, $cuadro->partido(1)))->toThrow(AvanceNoReversible::class);
});

test('revertir deshace también un cierre automático por bye en cadena', function () {
    // Cuadro de 8 con 5: el partido 2 (4 vs 5) alimenta la segunda ronda contra 1.
    $cuadro = cuadroDe(5);
    $avance = new AvanceDeCuadro;

    $cuadro = $avance->avanzar($cuadro, $cuadro->partido(2)->conGanador(4, resultado()));
    $segunda = $cuadro->partidosDeRonda(2)[0];

    expect($segunda->local)->toBe(1)->and($segunda->visitante)->toBe(4);

    $cuadro = $avance->revertir($cuadro, $cuadro->partido(2));

    expect($cuadro->partidosDeRonda(2)[0]->visitante)->toBeNull()
        ->and($cuadro->partidosDeRonda(2)[0]->local)->toBe(1)
        ->and($cuadro->partido(2)->ganador)->toBeNull();
});
