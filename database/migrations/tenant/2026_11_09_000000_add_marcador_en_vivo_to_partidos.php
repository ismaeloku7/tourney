<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El partido en pista: cuándo entró de verdad (la mesa o un jugador lo
 * empieza) y el marcador en vivo (sets cerrados y juegos del set en
 * curso), para que la cola y el público sepan cómo va. Cada paso se
 * salta si ya está hecho, para poder reintentar el despliegue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partidos', function (Blueprint $tabla) {
            if (! Schema::hasColumn('partidos', 'empezado_en')) {
                $tabla->dateTime('empezado_en')->nullable()->after('aviso_calentar_en');
            }
            if (! Schema::hasColumn('partidos', 'marcador_en_vivo')) {
                $tabla->json('marcador_en_vivo')->nullable()->after('empezado_en');
            }
        });
    }

    public function down(): void
    {
        Schema::table('partidos', function (Blueprint $tabla) {
            $tabla->dropColumn(['empezado_en', 'marcador_en_vivo']);
        });
    }
};
