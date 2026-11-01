<?php

namespace App\Actions\Plataforma;

use App\Enums\EstadoOrganizador;
use App\Enums\TipoDeEventoDeUso;
use App\Models\AuditoriaGlobal;
use App\Models\EventoUso;
use App\Models\Organizador;

/**
 * El enlace del correo: si el token es el suyo y no ha caducado, el
 * organizador pasa a activo y empieza a servir sus hosts.
 */
final class VerificarOrganizador
{
    /** Nulo si el token no vale o ha caducado. Un organizador ya activo vuelve tal cual. */
    public function handle(Organizador $organizador, string $token): ?Organizador
    {
        if ($organizador->estado === EstadoOrganizador::Activo && $organizador->verificado_en !== null) {
            return $organizador;
        }

        if ($organizador->estado !== EstadoOrganizador::Pendiente || $organizador->verificacion_token === null) {
            return null;
        }

        if (! hash_equals($organizador->verificacion_token, hash('sha256', $token))) {
            return null;
        }

        if ($organizador->verificacion_expira_en === null || $organizador->verificacion_expira_en->isPast()) {
            return null;
        }

        $organizador->forceFill([
            'estado' => EstadoOrganizador::Activo,
            'verificado_en' => now(),
            'verificacion_token' => null,
            'verificacion_expira_en' => null,
        ])->save();

        EventoUso::anotar($organizador, TipoDeEventoDeUso::OrganizadorVerificado);
        AuditoriaGlobal::anotar('verificar', $organizador, null, ['email' => $organizador->responsable_email]);

        return $organizador;
    }
}
