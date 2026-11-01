<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Si los jugadores se inscriben solos desde la web o si solo inscribe la
 * organización. Los torneos que ya existen siguen con la inscripción online.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('torneos', function (Blueprint $tabla) {
            $tabla->boolean('inscripcion_online')->default(true)->after('inscripcion_hasta');
        });
    }

    public function down(): void
    {
        Schema::table('torneos', function (Blueprint $tabla) {
            $tabla->dropColumn('inscripcion_online');
        });
    }
};
