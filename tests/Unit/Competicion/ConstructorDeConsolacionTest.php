<?php

use App\Competicion\ConstructorDeConsolacion;
use App\Competicion\ConstructorDeCuadro;
use App\Competicion\Datos\Clasificado;
use App\Competicion\Datos\ConfiguracionDeEliminatoria;
use App\Competicion\Datos\Partido;
use App\Competicion\Errores\ConfiguracionInvalida;
use App\Enums\TipoDeOrigen;

test('la consolación de perdedores de primera ronda tiene un hueco por partido, en orden', function () {
    $clasificados = [];
    for ($i = 1; $i <= 8; $i++) {
        $clasificados[] = new Clasificado($i, null, 1, "C{$i}", $i);
    }
    $configuracion = ConfiguracionDeEliminatoria::desdeArray(['consolacion' => 'perdedores_primera_ronda']);
    $principal = (new ConstructorDeCuadro)->construir($clasificados, $configuracion);

    $consolacion = (new ConstructorDeConsolacion)->desdePerdedores($principal, $configuracion);

    expect($consolacion->tamano)->toBe(4)
        ->and($consolacion->rondas)->toBe(2)
        ->and($consolacion->partidos)->toHaveCount(3)
        ->and($consolacion->avisos[0])->toContain('perdedores');

    $primera = $consolacion->partidosDeRonda(1);

    expect($primera[0]->origenLocal?->tipo)->toBe(TipoDeOrigen::PerdedorDe)
        ->and($primera[0]->origenLocal?->referencia)->toBe(1)
        ->and($primera[0]->origenVisitante?->referencia)->toBe(2)
        ->and($primera[1]->origenLocal?->referencia)->toBe(3)
        ->and($primera[1]->origenVisitante?->referencia)->toBe(4)
        ->and($primera[0]->local)->toBeNull();
});

test('un partido con bye en el principal no tiene perdedor y deja un bye en la consolación', function () {
    $clasificados = [];
    for ($i = 1; $i <= 6; $i++) {
        $clasificados[] = new Clasificado($i, null, 1, "C{$i}", $i);
    }
    $configuracion = ConfiguracionDeEliminatoria::desdeArray([]);
    $principal = (new ConstructorDeCuadro)->construir($clasificados, $configuracion);

    $consolacion = (new ConstructorDeConsolacion)->desdePerdedores($principal, $configuracion);
    $primera = $consolacion->partidosDeRonda(1);

    // Huecos de 8 con 6: [1, bye, 4, 5, 2, bye, 3, 6]: los partidos 1 y 3
    // (cabezas 1 y 2) tienen bye y no dejan perdedor.
    expect($primera[0]->origenLocal)->toBeNull()
        ->and($primera[0]->origenVisitante?->referencia)->toBe(2)
        ->and($primera[1]->origenLocal)->toBeNull()
        ->and($primera[1]->origenVisitante?->referencia)->toBe(4);
});

test('los no clasificados forman su cuadro con las mismas reglas que el principal', function () {
    $noClasificados = [
        new Clasificado(13, 1, 3, '3º A', 1),
        new Clasificado(23, 2, 3, '3º B', 2),
        new Clasificado(14, 1, 4, '4º A', 3),
        new Clasificado(24, 2, 4, '4º B', 4),
        new Clasificado(33, 3, 3, '3º C', 5),
        new Clasificado(34, 3, 4, '4º C', 6),
    ];

    $cuadro = (new ConstructorDeConsolacion)->construir($noClasificados, ConfiguracionDeEliminatoria::desdeArray([]));

    expect($cuadro->tamano)->toBe(8)
        ->and(array_filter(array_map(fn (Partido $p): ?int => $p->ganador, $cuadro->partidosDeRonda(1))))->toHaveCount(2);

    foreach ($cuadro->partidosDeRonda(1) as $partido) {
        if ($partido->local !== null && $partido->visitante !== null) {
            expect(intdiv($partido->local, 10))->not->toBe(intdiv($partido->visitante, 10));
        }
    }
});

test('un cuadro de 2 no tiene consolación de perdedores', function () {
    $configuracion = ConfiguracionDeEliminatoria::desdeArray([]);
    $principal = (new ConstructorDeCuadro)->construir([new Clasificado(1, null, 1, 'a', 1), new Clasificado(2, null, 1, 'b', 2)], $configuracion);

    expect(fn () => (new ConstructorDeConsolacion)->desdePerdedores($principal, $configuracion))->toThrow(ConfiguracionInvalida::class);
});
