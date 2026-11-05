<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los horarios en directo: la hora prevista de cada partido al planificar
 * (para saber cuánto se retrasa su pista) y cuándo se avisó a sus parejas
 * de que son las siguientes y de que pasen a calentar. Las horas pasan a
 * ser estimadas y se mueven durante el día: un partido que acabó puede
 * tener la misma hora de inicio que el que entra después en su pista, así
 * que deja de ser única por pista (los solapes los evitan el planificador
 * y «Hora y pista»).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('franjas', function (Blueprint $tabla) {
            $tabla->dateTime('inicio_previsto')->nullable()->after('fin');
            $tabla->dropUnique(['pista_id', 'inicio']);
            $tabla->index(['pista_id', 'inicio']);
        });

        Schema::table('partidos', function (Blueprint $tabla) {
            $tabla->dateTime('aviso_siguientes_en')->nullable()->after('aviso_proximo_en');
            $tabla->dateTime('aviso_calentar_en')->nullable()->after('aviso_siguientes_en');
        });
    }

    public function down(): void
    {
        Schema::table('franjas', function (Blueprint $tabla) {
            $tabla->dropIndex(['pista_id', 'inicio']);
            $tabla->unique(['pista_id', 'inicio']);
            $tabla->dropColumn('inicio_previsto');
        });

        Schema::table('partidos', function (Blueprint $tabla) {
            $tabla->dropColumn(['aviso_siguientes_en', 'aviso_calentar_en']);
        });
    }
};
