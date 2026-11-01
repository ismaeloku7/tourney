<?php

namespace App\Models\Concerns;

use App\Models\Categoria;
use App\Models\Fase;
use App\Models\FormatoPartido;
use App\Models\Inscripcion;
use App\Models\Jugador;
use App\Models\Participante;
use App\Models\Partido;
use App\Models\Pista;
use App\Models\Sede;
use App\Models\Torneo;
use App\Models\User;

/**
 * Qué pasa con lo que cuelga de un registro cuando va a la papelera.
 *
 * Borrar del todo lo resuelve la base con sus claves foráneas. Mandar a la
 * papelera no borra la fila, así que la base no hace nada: estas reglas
 * hacen a mano lo mismo que haría ella, con una diferencia, que lo que se
 * va con el registro va también a la papelera y vuelve con él.
 *
 * - `papelera`: va a la papelera con él, y vuelve si se restaura.
 * - `borrar`: se borra del todo (credenciales y vínculos que seguirían
 *   haciendo efecto apuntando a algo en la papelera).
 * - `vaciar`: la columna queda en nulo, como `nullOnDelete`. No vuelve.
 * - `conservar`: no se toca. Vuelve a verse al restaurar.
 *
 * Las tablas del torneo se añaden aquí con sus migraciones.
 */
final class ReglasDePapelera
{
    public const PAPELERA = 'papelera';

    public const BORRAR = 'borrar';

    public const VACIAR = 'vaciar';

    public const CONSERVAR = 'conservar';

    /** Tabla => modelo, de las que tienen papelera. */
    public const MODELOS = [
        'users' => User::class,
        'sedes' => Sede::class,
        'pistas' => Pista::class,
        'formatos_partido' => FormatoPartido::class,
        'torneos' => Torneo::class,
        'categorias' => Categoria::class,
        'jugadores' => Jugador::class,
        'participantes' => Participante::class,
        'inscripciones' => Inscripcion::class,
        'fases' => Fase::class,
        'partidos' => Partido::class,
    ];

    /**
     * Tabla => lista de [tabla hija, columna que apunta, regla].
     *
     * @var array<string, list<array{0: string, 1: string, 2: string}>>
     */
    public const VINCULOS = [
        'users' => [
            ['torneo_administradores', 'user_id', self::BORRAR],
            ['torneo_arbitros', 'user_id', self::BORRAR],
        ],
        'sedes' => [
            ['pistas', 'sede_id', self::VACIAR],
            ['torneos', 'sede_id', self::VACIAR],
        ],
        'pistas' => [
            ['torneo_pistas', 'pista_id', self::BORRAR],
            ['torneo_arbitros', 'pista_id', self::BORRAR],
            ['partidos', 'pista_id', self::VACIAR],
        ],
        'formatos_partido' => [
            ['categorias', 'formato_partido_id', self::VACIAR],
            ['fases', 'formato_partido_id', self::VACIAR],
            ['partidos', 'formato_partido_id', self::VACIAR],
        ],
        'torneos' => [
            ['categorias', 'torneo_id', self::PAPELERA],
        ],
        'categorias' => [
            ['participantes', 'categoria_id', self::PAPELERA],
            ['inscripciones', 'categoria_id', self::PAPELERA],
            ['fases', 'categoria_id', self::PAPELERA],
        ],
        'jugadores' => [],
        'participantes' => [
            ['inscripciones', 'participante_id', self::PAPELERA],
        ],
        'inscripciones' => [],
        'fases' => [
            ['partidos', 'fase_id', self::PAPELERA],
        ],
        'partidos' => [],
    ];
}
