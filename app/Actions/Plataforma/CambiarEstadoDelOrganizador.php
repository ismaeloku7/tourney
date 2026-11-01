<?php

namespace App\Actions\Plataforma;

use App\Enums\EstadoOrganizador;
use App\Models\AuditoriaGlobal;
use App\Models\Organizador;
use App\Models\UsuarioPlataforma;

/**
 * Activar, suspender o reactivar un organizador. Suspender no borra
 * nada: deja de servir sus hosts hasta que se reactive.
 */
class CambiarEstadoDelOrganizador
{
    public function handle(Organizador $organizador, EstadoOrganizador $estado, ?UsuarioPlataforma $quien = null, ?string $motivo = null): void
    {
        $anterior = $organizador->estado;

        $organizador->estado = $estado;
        $organizador->suspendido_en = $estado === EstadoOrganizador::Suspendido ? now() : null;
        $organizador->suspension_motivo = $estado === EstadoOrganizador::Suspendido ? $motivo : null;
        $organizador->save();

        AuditoriaGlobal::anotar($estado->value, $organizador, $quien, ['antes' => $anterior->value, 'motivo' => $motivo]);
    }
}
