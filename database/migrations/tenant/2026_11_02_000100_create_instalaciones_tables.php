<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Las instalaciones del organizador (sedes y pistas) y sus plantillas de
 * formato de partido, reutilizables entre torneos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sedes', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('nombre', 120);
            $tabla->string('direccion')->nullable();
            $tabla->string('mapa_url', 500)->nullable();
            $tabla->unsignedSmallInteger('orden')->default(0);
            $tabla->timestamps();
            $tabla->softDeletes();
        });

        Schema::create('pistas', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('sede_id')->nullable()->constrained('sedes')->nullOnDelete();
            $tabla->string('nombre', 60);
            $tabla->unsignedSmallInteger('orden')->default(0);
            $tabla->boolean('activa')->default(true);
            $tabla->timestamps();
            $tabla->softDeletes();
        });

        Schema::create('formatos_partido', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('nombre', 120);
            // 1, 3 o 5.
            $tabla->unsignedTinyInteger('sets')->default(3);
            // 4, 6 o 9 (set largo).
            $tabla->unsignedTinyInteger('juegos_por_set')->default(6);
            // 1 o 2.
            $tabla->unsignedTinyInteger('diferencia_para_ganar_set')->default(2);
            // Juegos iguales a los que se juega el tie-break (6, 4, 8); nulo, sin tie-break.
            $tabla->unsignedTinyInteger('tie_break_en')->nullable()->default(6);
            $tabla->unsignedTinyInteger('tie_break_puntos')->default(7);
            $tabla->boolean('super_tie_break_final')->default(false);
            $tabla->unsignedTinyInteger('super_tie_break_puntos')->default(10);
            // Informativo: no afecta al cálculo.
            $tabla->boolean('punto_de_oro')->default(true);
            // Partido por tiempo: al acabar gana quien va delante en juegos.
            $tabla->unsignedSmallInteger('por_tiempo_minutos')->nullable();
            // Minutos, para el planificador.
            $tabla->unsignedSmallInteger('duracion_estimada')->default(60);
            $tabla->boolean('es_predeterminado')->default(false);
            $tabla->timestamps();
            $tabla->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formatos_partido');
        Schema::dropIfExists('pistas');
        Schema::dropIfExists('sedes');
    }
};
