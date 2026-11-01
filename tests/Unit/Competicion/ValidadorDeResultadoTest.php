<?php

use App\Competicion\Datos\FormatoDePartido;
use App\Competicion\Datos\Resultado;
use App\Competicion\Datos\Set;
use App\Competicion\ValidadorDeResultado;
use App\Enums\ResultadoEspecial;

function validar(Resultado $resultado, ?FormatoDePartido $formato = null)
{
    return (new ValidadorDeResultado)->validar($resultado, $formato ?? new FormatoDePartido);
}

test('6-3 6-4 vale en el formato por defecto y gana el local', function () {
    $validacion = validar(new Resultado([new Set(6, 3), new Set(6, 4)]));

    expect($validacion->valido)->toBeTrue()
        ->and($validacion->ganaLocal)->toBeTrue()
        ->and($validacion->setsLocal)->toBe(2)
        ->and($validacion->setsVisitante)->toBe(0);
});

test('3-6 6-4 4-6 vale y gana el visitante', function () {
    $validacion = validar(new Resultado([new Set(3, 6), new Set(6, 4), new Set(4, 6)]));

    expect($validacion->valido)->toBeTrue()->and($validacion->ganaLocal)->toBeFalse()->and($validacion->setsVisitante)->toBe(2);
});

test('7-3 en un set a 6 no es posible', function () {
    $validacion = validar(new Resultado([new Set(7, 3), new Set(6, 4)]));

    expect($validacion->valido)->toBeFalse()->and($validacion->errores[0])->toContain('7-3');
});

test('7-6 exige los puntos del tie-break, y vale con ellos', function () {
    expect(validar(new Resultado([new Set(7, 6), new Set(6, 4)]))->errores[0])->toContain('faltan sus puntos')
        ->and(validar(new Resultado([new Set(7, 6, 7, 5), new Set(6, 4)]))->valido)->toBeTrue()
        ->and(validar(new Resultado([new Set(7, 6, 7, 6), new Set(6, 4)]))->errores[0])->toContain('2 de diferencia')
        ->and(validar(new Resultado([new Set(7, 6, 5, 7), new Set(6, 4)]))->errores[0])->toContain('otro lado');
});

test('7-5 se gana sin tie-break y 8-6 no existe con tie-break', function () {
    expect(validar(new Resultado([new Set(7, 5), new Set(6, 4)]))->valido)->toBeTrue()
        ->and(validar(new Resultado([new Set(7, 5, 7, 3), new Set(6, 4)]))->errores[0])->toContain('sobran')
        ->and(validar(new Resultado([new Set(8, 6), new Set(6, 4)]))->valido)->toBeFalse();
});

test('sin tie-break el set sigue hasta sacar dos juegos de diferencia', function () {
    $formato = new FormatoDePartido(tieBreakEn: null);

    expect(validar(new Resultado([new Set(8, 6), new Set(6, 4)]), $formato)->valido)->toBeTrue()
        ->and(validar(new Resultado([new Set(11, 9), new Set(6, 4)]), $formato)->valido)->toBeTrue()
        ->and(validar(new Resultado([new Set(7, 6), new Set(6, 4)]), $formato)->valido)->toBeFalse();
});

test('el super tie-break decide el tercer set', function () {
    $formato = new FormatoDePartido(superTieBreakFinal: true);
    $resultado = new Resultado([new Set(6, 3), new Set(4, 6), new Set(10, 8, esSuperTieBreak: true)]);

    expect(validar($resultado, $formato)->valido)->toBeTrue()
        ->and(validar($resultado, $formato)->ganaLocal)->toBeTrue()
        ->and(validar(new Resultado([new Set(6, 3), new Set(4, 6), new Set(6, 4)]), $formato)->errores[0])->toContain('super tie-break')
        ->and(validar(new Resultado([new Set(6, 3), new Set(4, 6), new Set(10, 9, esSuperTieBreak: true)]), $formato)->errores[0])->toContain('2 de diferencia')
        ->and(validar(new Resultado([new Set(10, 3, esSuperTieBreak: true), new Set(6, 4)]), $formato)->errores[0])->toContain('no puede ser un super tie-break')
        ->and(validar(new Resultado([new Set(6, 3), new Set(6, 4)]), $formato)->valido)->toBeTrue();
});

test('un set a 4 y un set a 9 tienen sus propias reglas', function () {
    $aCuatro = new FormatoDePartido(sets: 1, juegosPorSet: 4, tieBreakEn: 4);
    $aNueve = new FormatoDePartido(sets: 1, juegosPorSet: 9, tieBreakEn: 8);

    expect(validar(new Resultado([new Set(4, 2)]), $aCuatro)->valido)->toBeTrue()
        ->and(validar(new Resultado([new Set(5, 4, 7, 2)]), $aCuatro)->valido)->toBeTrue()
        ->and(validar(new Resultado([new Set(6, 4)]), $aCuatro)->valido)->toBeFalse()
        ->and(validar(new Resultado([new Set(9, 7)]), $aNueve)->valido)->toBeTrue()
        ->and(validar(new Resultado([new Set(9, 8, 7, 4)]), $aNueve)->valido)->toBeTrue()
        ->and(validar(new Resultado([new Set(9, 8)]), $aNueve)->valido)->toBeFalse()
        ->and(validar(new Resultado([new Set(10, 8)]), $aNueve)->valido)->toBeFalse();
});

test('un tercer set de más y un partido sin decidir no valen', function () {
    expect(validar(new Resultado([new Set(6, 3), new Set(6, 4), new Set(6, 2)]))->errores[0])->toContain('sobra')
        ->and(validar(new Resultado([new Set(6, 3)]))->errores[0])->toContain('Faltan sets')
        ->and(validar(new Resultado([]))->errores[0])->toContain('al menos un set')
        ->and(validar(new Resultado([new Set(6, 6)]))->errores[0])->toContain('empate');
});

test('un walkover exige ganador y un no presentado no lo tiene', function () {
    expect(validar(new Resultado([], ResultadoEspecial::Walkover))->errores[0])->toContain('quién gana')
        ->and(validar(new Resultado([], ResultadoEspecial::Walkover, ganaLocal: false)))->valido->toBeTrue()->ganaLocal->toBeFalse()
        ->and(validar(new Resultado([new Set(6, 2), new Set(3, 1)], ResultadoEspecial::Retirada, ganaLocal: true)))->valido->toBeTrue()->setsLocal->toBe(2)
        ->and(validar(new Resultado([], ResultadoEspecial::NoPresentado)))->valido->toBeTrue()->ganaLocal->toBeNull()
        ->and(validar(new Resultado([], ResultadoEspecial::NoPresentado, ganaLocal: true))->valido)->toBeFalse();
});

test('por tiempo gana quien va delante en juegos y el empate exige decidir', function () {
    $formato = new FormatoDePartido(sets: 1, tieBreakEn: null, porTiempoMinutos: 20);

    expect(validar(new Resultado([new Set(5, 3)]), $formato))->valido->toBeTrue()->ganaLocal->toBeTrue()
        ->and(validar(new Resultado([new Set(2, 7)]), $formato))->valido->toBeTrue()->ganaLocal->toBeFalse()
        ->and(validar(new Resultado([new Set(4, 4)]), $formato)->errores[0])->toContain('empate a juegos')
        ->and(validar(new Resultado([new Set(4, 4)], ganaLocal: false), $formato))->valido->toBeTrue()->ganaLocal->toBeFalse()
        ->and(validar(new Resultado([new Set(4, 4), new Set(1, 0)]), $formato)->valido)->toBeFalse();
});
