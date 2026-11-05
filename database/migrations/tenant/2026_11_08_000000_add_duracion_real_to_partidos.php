<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cuánto duró de verdad cada partido jugado en directo (sin el
 * calentamiento), con qué formato y si las parejas iban igualadas de
 * nivel: de ahí aprende el planificador la duración de los siguientes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partidos', function (Blueprint $tabla) {
            $tabla->unsignedSmallInteger('minutos_jugados')->nullable()->after('registrado_en');
            $tabla->unsignedBigInteger('formato_medido_id')->nullable()->after('minutos_jugados');
            $tabla->boolean('parejas_igualadas')->nullable()->after('formato_medido_id');
            $tabla->index(['formato_medido_id', 'minutos_jugados']);
        });
    }

    public function down(): void
    {
        Schema::table('partidos', function (Blueprint $tabla) {
            $tabla->dropIndex(['formato_medido_id', 'minutos_jugados']);
            $tabla->dropColumn(['minutos_jugados', 'formato_medido_id', 'parejas_igualadas']);
        });
    }
};
