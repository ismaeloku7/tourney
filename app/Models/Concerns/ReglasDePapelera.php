<?php

namespace App\Models\Concerns;

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
    ];

    /**
     * Tabla => lista de [tabla hija, columna que apunta, regla].
     *
     * @var array<string, list<array{0: string, 1: string, 2: string}>>
     */
    public const VINCULOS = [
        'users' => [],
    ];
}
