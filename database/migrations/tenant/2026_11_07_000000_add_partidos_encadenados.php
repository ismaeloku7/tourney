<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Partidos encadenados: el torneo elige si los partidos van a hora fija
 * o uno detrás de otro por pista (con calentamiento antes del primero de
 * cada pareja y entre partidos), y cada partido guarda su orden en la
 * pista, la hora estimada, cuándo empezó y acabó de verdad, el marcador
 * en vivo y qué avisos se han mandado ya a la pareja siguiente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('torneos', function (Blueprint $tabla) {
            $tabla->string('modo_horario', 12)->default('fijo')->after('margen_entre_partidos_minutos');
            $tabla->unsignedSmallInteger('calentamiento_primero')->default(15)->after('modo_horario');
            $tabla->unsignedSmallInteger('calentamiento_resto')->default(5)->after('calentamiento_primero');
        });

        Schema::table('partidos', function (Blueprint $tabla) {
            $tabla->unsignedSmallInteger('orden_en_pista')->nullable()->after('franja_id');
            $tabla->timestamp('inicio_estimado')->nullable()->after('orden_en_pista');
            $tabla->timestamp('empezado_en')->nullable()->after('inicio_estimado');
            $tabla->timestamp('terminado_en')->nullable()->after('empezado_en');
            $tabla->json('marcador_en_vivo')->nullable()->after('terminado_en');
            $tabla->json('avisos_siguiente')->nullable()->after('marcador_en_vivo');
        });
    }

    public function down(): void
    {
        Schema::table('partidos', function (Blueprint $tabla) {
            $tabla->dropColumn(['orden_en_pista', 'inicio_estimado', 'empezado_en', 'terminado_en', 'marcador_en_vivo', 'avisos_siguiente']);
        });

        Schema::table('torneos', function (Blueprint $tabla) {
            $tabla->dropColumn(['modo_horario', 'calentamiento_primero', 'calentamiento_resto']);
        });
    }
};
