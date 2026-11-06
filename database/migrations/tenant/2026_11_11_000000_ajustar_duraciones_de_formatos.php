<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Duraciones de serie más cercanas a lo que se juega (unos 3 minutos por
 * juego con punto de oro): un set a 9 (hasta 17 juegos) ronda los 50
 * minutos; dos sets con super tie-break (hasta 26 juegos más el super
 * tie-break), los 75; al mejor de 3, los 90. Solo cambia los formatos que
 * siguen con la duración de serie de antes: si el organizador la ha
 * puesto a mano, se queda la suya. Repetible.
 */
return new class extends Migration
{
    /** nombre → [antes, ahora] */
    private const DURACIONES = [
        'Un set a 9' => [60, 50],
        'Dos sets + super tie-break' => [60, 75],
        'Al mejor de 3 sets a 6' => [75, 90],
    ];

    public function up(): void
    {
        foreach (self::DURACIONES as $nombre => [$antes, $ahora]) {
            DB::table('formatos_partido')->where('nombre', $nombre)->where('duracion_estimada', $antes)
                ->update(['duracion_estimada' => $ahora, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        foreach (self::DURACIONES as $nombre => [$antes, $ahora]) {
            DB::table('formatos_partido')->where('nombre', $nombre)->where('duracion_estimada', $ahora)
                ->update(['duracion_estimada' => $antes]);
        }
    }
};
