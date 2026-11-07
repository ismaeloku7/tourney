<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Las fases «Todo a mano» que se quedaron sin la lista de grupos al
 * empezarlas de nuevo: sin ella su configuración no vale y el diálogo de
 * los grupos no se abre. Se les pone la lista vacía, para volver a
 * colocar las parejas. Repetible.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('fases')->whereNotNull('configuracion')->get(['id', 'configuracion']) as $fase) {
            $configuracion = json_decode((string) $fase->configuracion, true);
            if (! is_array($configuracion) || ($configuracion['modo_de_sorteo'] ?? null) !== 'manual' || is_array($configuracion['grupos_manuales'] ?? null)) {
                continue;
            }

            $configuracion['grupos_manuales'] = [];
            DB::table('fases')->where('id', $fase->id)->update(['configuracion' => json_encode($configuracion)]);
        }
    }

    public function down(): void {}
};
