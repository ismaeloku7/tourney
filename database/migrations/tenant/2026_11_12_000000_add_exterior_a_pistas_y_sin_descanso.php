<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Las pistas al aire libre (`exterior`): en la fase de grupos se procura
 * que todas las parejas jueguen alguna vez fuera; los cuadros, dentro.
 *
 * Y el descanso mínimo deja de ser de 30 minutos de serie: quien viene de
 * jugar enlaza (no calienta) y el planificador ya evita que nadie juegue
 * tres seguidos. Los torneos que seguían con los 30 de serie pasan a 0.
 * Repetible.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pistas', 'exterior')) {
            Schema::table('pistas', function (Blueprint $tabla) {
                $tabla->boolean('exterior')->default(false)->after('activa');
            });
        }

        DB::table('torneos')->where('descanso_minimo_minutos', 30)->update(['descanso_minimo_minutos' => 0]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('pistas', 'exterior')) {
            Schema::table('pistas', function (Blueprint $tabla) {
                $tabla->dropColumn('exterior');
            });
        }
    }
};
