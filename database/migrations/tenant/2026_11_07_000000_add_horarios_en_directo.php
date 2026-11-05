<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los horarios en directo: la hora prevista de cada partido al planificar
 * (para saber cuánto se retrasa su pista) y cuándo se avisó a sus parejas
 * de que son las siguientes y de que pasen a calentar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('franjas', function (Blueprint $tabla) {
            $tabla->dateTime('inicio_previsto')->nullable()->after('fin');
        });

        Schema::table('partidos', function (Blueprint $tabla) {
            $tabla->dateTime('aviso_siguientes_en')->nullable()->after('aviso_proximo_en');
            $tabla->dateTime('aviso_calentar_en')->nullable()->after('aviso_siguientes_en');
        });
    }

    public function down(): void
    {
        Schema::table('franjas', function (Blueprint $tabla) {
            $tabla->dropColumn('inicio_previsto');
        });

        Schema::table('partidos', function (Blueprint $tabla) {
            $tabla->dropColumn(['aviso_siguientes_en', 'aviso_calentar_en']);
        });
    }
};
