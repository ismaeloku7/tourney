<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qué enseña la web pública de cada torneo: la lista de inscritos, los
 * horarios y cada fase (grupos, cuadro, finales…). Por defecto, todo,
 * como hasta ahora.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('torneos', function (Blueprint $tabla) {
            $tabla->boolean('web_inscritos')->default(true)->after('inscripcion_online');
            $tabla->boolean('web_horarios')->default(true)->after('web_inscritos');
        });

        Schema::table('fases', function (Blueprint $tabla) {
            $tabla->boolean('visible_en_web')->default(true)->after('estado');
        });
    }

    public function down(): void
    {
        Schema::table('torneos', function (Blueprint $tabla) {
            $tabla->dropColumn(['web_inscritos', 'web_horarios']);
        });

        Schema::table('fases', function (Blueprint $tabla) {
            $tabla->dropColumn('visible_en_web');
        });
    }
};
