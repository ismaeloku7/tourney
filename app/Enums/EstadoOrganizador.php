<?php

namespace App\Enums;

use App\Enums\Concerns\ConEtiqueta;

/**
 * Ciclo de vida de un organizador: pendiente (registrado, sin verificar el
 * correo), activo, suspendido (impago o abuso: el panel se bloquea y las
 * páginas públicas avisan) y baja (borrado de la base programado a los
 * 30 días). Solo un organizador activo sirve sus hosts.
 */
enum EstadoOrganizador: string
{
    use ConEtiqueta;

    case Pendiente = 'pendiente';
    case Activo = 'activo';
    case Suspendido = 'suspendido';
    case Baja = 'baja';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente de verificar',
            self::Activo => 'Activo',
            self::Suspendido => 'Suspendido',
            self::Baja => 'Dado de baja',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pendiente => 'warning',
            self::Activo => 'success',
            self::Suspendido => 'danger',
            self::Baja => 'gray',
        };
    }
}
