<?php

use App\Competicion\Datos\ConfiguracionDeEliminatoria;
use App\Competicion\Datos\ConfiguracionDeGrupos;
use App\Competicion\Datos\DisponibilidadDePista;
use App\Competicion\Datos\FormatoDePartido;
use App\Competicion\Datos\Origen;
use App\Competicion\Datos\Partido;
use App\Competicion\Datos\Puntuacion;
use App\Competicion\Datos\ReglaDeClasificados;
use App\Competicion\Datos\Resultado;
use App\Competicion\Datos\Set;
use App\Competicion\Errores\ConfiguracionInvalida;
use App\Enums\CriterioDeDesempate;
use App\Enums\EstadoPartido;
use App\Enums\ModoDeSorteo;
use App\Enums\OrigenDeConsolacion;
use App\Enums\ReglaDeCruces;
use App\Enums\TipoDeOrigen;

test('el formato por defecto es al mejor de 3 sets a 6 con tie-break y sobrevive al viaje por array', function () {
    $formato = new FormatoDePartido;

    expect($formato->setsParaGanar())->toBe(2)
        ->and(FormatoDePartido::desdeArray($formato->toArray()))->toEqual($formato)
        ->and((new FormatoDePartido(sets: 5))->setsParaGanar())->toBe(3)
        ->and((new FormatoDePartido(sets: 1))->setsParaGanar())->toBe(1);
});

test('un formato inválido lanza con todos sus errores', function () {
    expect(fn () => FormatoDePartido::desdeArray(['sets' => 2, 'juegos_por_set' => 7, 'diferencia_para_ganar_set' => 3]))
        ->toThrow(ConfiguracionInvalida::class, 'sets');

    try {
        FormatoDePartido::desdeArray(['sets' => 2, 'juegos_por_set' => 7, 'diferencia_para_ganar_set' => 3, 'tie_break_en' => 5]);
    } catch (ConfiguracionInvalida $e) {
        expect($e->errores)->toHaveCount(4);
    }
});

test('el tie-break de un set a 9 se juega a 8 iguales, y el super tie-break exige más de un set', function () {
    expect(FormatoDePartido::desdeArray(['juegos_por_set' => 9, 'tie_break_en' => 8])->tieBreakEn)->toBe(8)
        ->and(fn () => FormatoDePartido::desdeArray(['juegos_por_set' => 9, 'tie_break_en' => 9]))->toThrow(ConfiguracionInvalida::class)
        ->and(fn () => FormatoDePartido::desdeArray(['sets' => 1, 'super_tie_break_final' => true]))->toThrow(ConfiguracionInvalida::class)
        ->and(FormatoDePartido::desdeArray(['juegos_por_set' => 6, 'tie_break_en' => null])->tieBreakEn)->toBeNull();
});

test('la puntuación por defecto es 2, 0, 2, 0, 0 y el walkover cuenta 2-0 y 6-0', function () {
    $puntuacion = Puntuacion::desdeArray([]);

    expect($puntuacion)->victoria->toBe(2)->derrota->toBe(0)->walkoverFavor->toBe(2)->walkoverContra->toBe(0)->noJugado->toBe(0)
        ->and($puntuacion->porSetGanado)->toBeNull()
        ->and($puntuacion->walkoverSets)->toBe(2)
        ->and($puntuacion->walkoverJuegosPorSet)->toBe(6)
        ->and(fn () => Puntuacion::desdeArray(['victoria' => -1]))->toThrow(ConfiguracionInvalida::class);
});

test('la configuración de grupos exige número o tamaño, y no los dos', function () {
    expect(fn () => ConfiguracionDeGrupos::desdeArray(['numero_de_grupos' => null, 'tamano_de_grupo' => null]))->toThrow(ConfiguracionInvalida::class, 'número de grupos o el tamaño')
        ->and(fn () => ConfiguracionDeGrupos::desdeArray(['numero_de_grupos' => 2, 'tamano_de_grupo' => 4]))->toThrow(ConfiguracionInvalida::class, 'alternativos')
        ->and(fn () => ConfiguracionDeGrupos::desdeArray(['tamano_de_grupo' => 1]))->toThrow(ConfiguracionInvalida::class, 'al menos 2')
        ->and(fn () => ConfiguracionDeGrupos::desdeArray(['tamano_de_grupo' => 4, 'criterios_de_desempate' => ['puntos', 'puntos']]))->toThrow(ConfiguracionInvalida::class, 'no se repite')
        ->and(fn () => ConfiguracionDeGrupos::desdeArray(['tamano_de_grupo' => 4, 'criterios_de_desempate' => ['inventado']]))->toThrow(ConfiguracionInvalida::class, 'no existe')
        ->and(fn () => ConfiguracionDeGrupos::desdeArray(['modo_de_sorteo' => 'manual']))->toThrow(ConfiguracionInvalida::class, 'manual');
});

test('la configuración de grupos completa va y vuelve por array', function () {
    $configuracion = ConfiguracionDeGrupos::desdeArray([
        'numero_de_grupos' => 3,
        'modo_de_sorteo' => 'cabezas_de_serie',
        'semilla' => 42,
        'partidos_por_participante' => 2,
        'ida_y_vuelta' => true,
        'puntuacion' => ['victoria' => 3, 'por_set_ganado' => 1],
        'criterios_de_desempate' => ['puntos', 'diferencia_de_sets', 'sorteo'],
        'clasificados' => ['por_grupo' => 1, 'mejores_de_posicion' => 2, 'cuantos_mejores' => 2],
    ]);

    expect($configuracion->numeroDeGrupos)->toBe(3)
        ->and($configuracion->tamanoDeGrupo)->toBeNull()
        ->and($configuracion->modoDeSorteo)->toBe(ModoDeSorteo::CabezasDeSerie)
        ->and($configuracion->semilla)->toBe(42)
        ->and($configuracion->partidosPorParticipante)->toBe(2)
        ->and($configuracion->idaYVuelta)->toBeTrue()
        ->and($configuracion->puntuacion->victoria)->toBe(3)
        ->and($configuracion->puntuacion->porSetGanado)->toBe(1)
        ->and($configuracion->criteriosDeDesempate)->toBe([CriterioDeDesempate::Puntos, CriterioDeDesempate::DiferenciaDeSets, CriterioDeDesempate::Sorteo])
        ->and($configuracion->clasificados->cuantosMejores)->toBe(2)
        ->and(ConfiguracionDeGrupos::desdeArray($configuracion->toArray()))->toEqual($configuracion)
        ->and($configuracion->conSemilla(7)->semilla)->toBe(7);
});

test('los grupos manuales se normalizan a listas de enteros', function () {
    $configuracion = ConfiguracionDeGrupos::desdeArray(['modo_de_sorteo' => 'manual', 'grupos_manuales' => [['1', '2'], [3, 4]]]);

    expect($configuracion->gruposManuales)->toBe([[1, 2], [3, 4]])
        ->and($configuracion->numeroDeGrupos)->toBeNull();
});

test('la regla de clasificados exige coherencia entre por grupo y mejores', function () {
    expect(fn () => ReglaDeClasificados::desdeArray(['por_grupo' => 2, 'mejores_de_posicion' => 2, 'cuantos_mejores' => 1]))->toThrow(ConfiguracionInvalida::class, 'después')
        ->and(fn () => ReglaDeClasificados::desdeArray(['por_grupo' => 2, 'cuantos_mejores' => 2]))->toThrow(ConfiguracionInvalida::class, 'de cuál')
        ->and(fn () => ReglaDeClasificados::desdeArray(['por_grupo' => 0]))->toThrow(ConfiguracionInvalida::class, 'Alguien')
        ->and(ReglaDeClasificados::desdeArray(['por_grupo' => 2, 'mejores_de_posicion' => 3, 'cuantos_mejores' => 0])->mejoresDePosicion)->toBeNull();
});

test('la configuración de eliminatoria exige potencia de 2 y cruces en modo manual', function () {
    expect(fn () => ConfiguracionDeEliminatoria::desdeArray(['tamano_de_cuadro' => 12]))->toThrow(ConfiguracionInvalida::class, 'potencia')
        ->and(fn () => ConfiguracionDeEliminatoria::desdeArray(['regla_de_cruces' => 'manual']))->toThrow(ConfiguracionInvalida::class, 'cruces')
        ->and(fn () => ConfiguracionDeEliminatoria::desdeArray(['formatos_por_ronda' => ['x' => 1]]))->toThrow(ConfiguracionInvalida::class, 'ronda');

    $configuracion = ConfiguracionDeEliminatoria::desdeArray([
        'tamano_de_cuadro' => 16,
        'regla_de_cruces' => 'manual',
        'cruces_manuales' => [[1, 2], [3, null]],
        'tercer_y_cuarto_puesto' => true,
        'consolacion' => 'perdedores_primera_ronda',
        'formatos_por_ronda' => ['1' => 5, '3' => 6],
    ]);

    expect($configuracion->tamanoDeCuadro)->toBe(16)
        ->and($configuracion->reglaDeCruces)->toBe(ReglaDeCruces::Manual)
        ->and($configuracion->crucesManuales)->toBe([[1, 2], [3, null]])
        ->and($configuracion->consolacion)->toBe(OrigenDeConsolacion::PerdedoresPrimeraRonda)
        ->and($configuracion->formatosPorRonda)->toBe([1 => 5, 3 => 6])
        ->and(ConfiguracionDeEliminatoria::desdeArray($configuracion->toArray()))->toEqual($configuracion);
});

test('la disponibilidad de pista valida fechas, horas y orden', function () {
    $disponibilidad = DisponibilidadDePista::desdeArray([
        ['fecha' => '2026-11-14', 'desde' => '09:00', 'hasta' => '14:00'],
        ['fecha' => '2026-11-14', 'desde' => '16:00', 'hasta' => '21:30'],
    ]);

    expect($disponibilidad->tramos)->toHaveCount(2)
        ->and($disponibilidad->toArray()[1]['hasta'])->toBe('21:30')
        ->and(fn () => DisponibilidadDePista::desdeArray([]))->toThrow(ConfiguracionInvalida::class, 'al menos un tramo')
        ->and(fn () => DisponibilidadDePista::desdeArray([['fecha' => '14/11/2026', 'desde' => '9:00', 'hasta' => '08:00']]))->toThrow(ConfiguracionInvalida::class);

    try {
        DisponibilidadDePista::desdeArray([['fecha' => '2026-02-30', 'desde' => '10:00', 'hasta' => '09:00']]);
    } catch (ConfiguracionInvalida $e) {
        expect($e->errores)->toHaveCount(2);
    }
});

test('un partido es inmutable: con(), conGanador() y vacio() devuelven copias', function () {
    $partido = new Partido(id: 7, ronda: 1, posicion: 2, local: 10, visitante: 20);
    $resultado = new Resultado([new Set(6, 3), new Set(6, 4)]);

    $jugado = $partido->conGanador(10, $resultado);

    expect($partido->estado)->toBe(EstadoPartido::Pendiente)
        ->and($partido->ganador)->toBeNull()
        ->and($jugado->estado)->toBe(EstadoPartido::Jugado)
        ->and($jugado->ganador)->toBe(10)
        ->and($jugado->perdedor())->toBe(20)
        ->and($jugado->cerrado())->toBeTrue()
        ->and($jugado->resultado?->setsGanadosPorLocal())->toBe(2)
        ->and($jugado->vacio()->ganador)->toBeNull()
        ->and($jugado->vacio()->estado)->toBe(EstadoPartido::Pendiente)
        ->and($jugado->vacio()->local)->toBe(10)
        ->and($partido->con(local: null, origenLocal: Origen::ganadorDe(3))->tieneHuecoPendiente())->toBeTrue()
        ->and($partido->rivalDe(10))->toBe(20)
        ->and($partido->rivalDe(99))->toBeNull()
        ->and($partido->juega(20))->toBeTrue();
});

test('un origen se etiqueta y viaja por array', function () {
    expect(Origen::ganadorDe(3)->etiqueta())->toBe('Ganador P3')
        ->and(Origen::perdedorDe(3)->etiqueta())->toBe('Perdedor P3')
        ->and(Origen::posicionDeGrupo(5, 1)->etiqueta('A'))->toBe('1º grupo A')
        ->and(Origen::clasificado(4)->etiqueta())->toBe('Clasificado 4')
        ->and(Origen::ganadorDe(3)->apuntaA(3))->toBeTrue()
        ->and(Origen::posicionDeGrupo(3, 1)->apuntaA(3))->toBeFalse()
        ->and(Origen::desdeArray(Origen::posicionDeGrupo(5, 2)->toArray()))->toEqual(new Origen(TipoDeOrigen::PosicionDeGrupo, 5, 2))
        ->and(fn () => Origen::desdeArray(['tipo' => 'nada']))->toThrow(ConfiguracionInvalida::class);
});

test('un resultado cuenta sets y juegos, sin contar el super tie-break como juegos', function () {
    $resultado = new Resultado([new Set(6, 3), new Set(4, 6), new Set(10, 7, esSuperTieBreak: true)]);

    expect($resultado->setsGanadosPorLocal())->toBe(2)
        ->and($resultado->setsGanadosPorVisitante())->toBe(1)
        ->and($resultado->juegosDeLocal())->toBe(10)
        ->and($resultado->juegosDeVisitante())->toBe(9);
});
