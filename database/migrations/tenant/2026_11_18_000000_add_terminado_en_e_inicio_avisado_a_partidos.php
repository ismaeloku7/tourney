<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los partidos encadenados en directo:
 * - `terminado_en`: cuándo dejó la pista un partido sin resultado todavía,
 *   porque el siguiente de su pista ha empezado (la pista ya está libre).
 * - `inicio_avisado`: la hora estimada que se les dijo a sus parejas en el
 *   último aviso, para no avisar de nuevo por cambios de un minuto.
 * Repetible.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['terminado_en', 'inicio_avisado'] as $columna) {
            if (! Schema::hasColumn('partidos', $columna)) {
                Schema::table('partidos', function (Blueprint $tabla) use ($columna) {
                    $tabla->timestamp($columna)->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['terminado_en', 'inicio_avisado'] as $columna) {
            if (Schema::hasColumn('partidos', $columna)) {
                Schema::table('partidos', fn (Blueprint $tabla) => $tabla->dropColumn($columna));
            }
        }
    }
};
