<?php

namespace App\Tenancy;

/**
 * Para todo modelo que vive en la base del organizador: equipo, sedes,
 * pistas, jugadores, torneos, partidos…
 *
 * Sin organizador activo, el modelo no tiene base: lanza
 * `SinOrganizadorActual` en vez de buscar en la central.
 */
trait EnLaBaseDelOrganizador
{
    public function getConnectionName(): string
    {
        if (! tenancy()->initialized) {
            throw new SinOrganizadorActual;
        }

        return 'tenant';
    }
}
