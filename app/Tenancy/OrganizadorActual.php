<?php

namespace App\Tenancy;

use App\Models\Organizador;

/**
 * El organizador de la petición, del comando o del job en curso, encima
 * de stancl/tenancy: con un organizador activo, la conexión por defecto
 * es su base.
 *
 * El único modo de cambiar de organizador fuera del middleware de host es
 * `ejecutarComo()`. Si algo cruza organizadores, se ve en esa llamada.
 */
class OrganizadorActual
{
    public function hay(): bool
    {
        return tenancy()->initialized;
    }

    public function organizador(): Organizador
    {
        $organizador = tenant();

        if (! $organizador instanceof Organizador) {
            throw new SinOrganizadorActual;
        }

        return $organizador;
    }

    public function slug(): string
    {
        return $this->organizador()->id;
    }

    /**
     * Ejecuta la acción con `$organizador` como organizador actual y deja
     * después el que hubiera antes, también si la acción lanza una
     * excepción.
     *
     * @template T
     *
     * @param  callable(Organizador): T  $accion
     * @return T
     */
    public function ejecutarComo(Organizador $organizador, callable $accion): mixed
    {
        $anterior = tenant();

        tenancy()->initialize($organizador);

        try {
            return $accion($organizador);
        } finally {
            if ($anterior !== null) {
                tenancy()->initialize($anterior);
            } else {
                tenancy()->end();
            }
        }
    }
}
