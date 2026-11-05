<?php

use App\Competicion\EstimadorDeDuracion;

/** @return list<array{formato: int, igualados: bool|null, minutos: int}> */
function medidos(int $formato, array $minutos, ?bool $igualados = null): array
{
    return array_map(fn (int $m): array => ['formato' => $formato, 'igualados' => $igualados, 'minutos' => $m], $minutos);
}

test('sin cinco partidos medidos de su formato, la duración estimada a mano', function () {
    $estimador = new EstimadorDeDuracion(medidos(1, [70, 80, 90, 100]));

    expect($estimador->minutos(1, null, 60))->toBe(60)
        ->and($estimador->minutos(2, null, 45))->toBe(45)
        ->and($estimador->resumen(1))->toBeNull();
});

test('con cinco o más, la mediana de su formato', function () {
    expect((new EstimadorDeDuracion(medidos(1, [50, 90, 70, 60, 200])))->minutos(1, null, 60))->toBe(70)
        ->and((new EstimadorDeDuracion(medidos(1, [50, 60, 70, 80, 90, 100])))->minutos(1, null, 60))->toBe(75)
        ->and((new EstimadorDeDuracion(medidos(1, [50, 60, 70, 80, 90, 100])))->resumen(1))->toBe(['minutos' => 75, 'partidos' => 6]);
});

test('entre parejas igualadas o no, su propia mediana si hay bastantes; si no, la del formato', function () {
    $estimador = new EstimadorDeDuracion([
        ...medidos(1, [90, 95, 100, 85, 92], true),
        ...medidos(1, [55, 60, 58, 62, 50], false),
        ...medidos(2, [40, 41, 42], true),
        ...medidos(2, [45, 46], false),
    ]);

    expect($estimador->minutos(1, true, 60))->toBe(92)
        ->and($estimador->minutos(1, false, 60))->toBe(58)
        ->and($estimador->minutos(1, null, 60))->toBe(74)
        // Del formato 2 hay cinco en total, pero no cinco de cada tipo.
        ->and($estimador->minutos(2, true, 30))->toBe(42)
        ->and($estimador->partidosMedidos())->toBe(15);
});

test('dos parejas van igualadas con hasta medio punto de diferencia de nivel', function () {
    expect(EstimadorDeDuracion::igualados(3.0, 3.5))->toBeTrue()
        ->and(EstimadorDeDuracion::igualados(3.0, 3.75))->toBeFalse()
        ->and(EstimadorDeDuracion::igualados(null, 3.0))->toBeNull();
});
