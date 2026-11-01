<?php

namespace App\Actions\Plataforma;

use App\Enums\EstadoOrganizador;
use App\Models\Plan;

/**
 * Lo que hace falta para dar de alta un organizador. La contraseña del
 * propietario viene ya decidida (del formulario de alta o del comando):
 * si es nula, se genera una al azar y la acción la devuelve una sola vez.
 */
final readonly class DatosDeOrganizadorNuevo
{
    public function __construct(
        public string $slug,
        public string $nombre,
        public string $responsableNombre,
        public string $responsableEmail,
        public ?string $responsablePassword = null,
        public ?string $dominio = null,
        public string $plan = Plan::GRATIS,
        public EstadoOrganizador $estado = EstadoOrganizador::Activo,
    ) {}
}
