<?php

namespace App\Tenancy;

use RuntimeException;

/**
 * Se ha pedido el organizador, o un modelo de su base, sin organizador
 * activo.
 *
 * Sin organizador, la conexión por defecto es la central: es mejor fallar
 * que buscar tablas del organizador donde no están.
 */
class SinOrganizadorActual extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('No hay organizador actual. Lo fija el middleware de host u OrganizadorActual::ejecutarComo().');
    }
}
