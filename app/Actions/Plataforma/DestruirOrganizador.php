<?php

namespace App\Actions\Plataforma;

use App\Models\AuditoriaGlobal;
use App\Models\Organizador;
use App\Models\UsuarioPlataforma;

/**
 * Borra un organizador del todo: su base (stancl la elimina al borrar la
 * fila), sus dominios y su fila en la central. No hay vuelta atrás: la
 * baja normal es `programar_baja`, con exportación previa y 30 días de
 * margen (fase 4). Esto es para pruebas y para altas equivocadas.
 */
class DestruirOrganizador
{
    public function handle(Organizador $organizador, ?UsuarioPlataforma $quien = null): void
    {
        $nombre = $organizador->nombre;
        $slug = $organizador->id;

        $activo = tenant();

        if ($activo instanceof Organizador && $activo->is($organizador)) {
            tenancy()->end();
        }

        $organizador->delete();

        AuditoriaGlobal::anotar('destruir', null, $quien, ['slug' => $slug, 'nombre' => $nombre]);
    }
}
