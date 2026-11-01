<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La competición de cada categoría: fases, grupos con sus participantes,
 * partidos con sus sets y las clasificaciones materializadas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fases', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('categoria_id')->constrained('categorias')->cascadeOnDelete();
            // TipoDeFase.
            $tabla->string('tipo', 16);
            $tabla->string('nombre', 80);
            $tabla->unsignedSmallInteger('orden')->default(1);
            // El DTO de configuración según el tipo (ConfiguracionDeGrupos o
            // ConfiguracionDeEliminatoria), como lo devuelve toArray().
            $tabla->json('configuracion');
            // Sobrescribe el de la categoría.
            $tabla->foreignId('formato_partido_id')->nullable()->constrained('formatos_partido')->nullOnDelete();
            // EstadoFase.
            $tabla->string('estado', 12)->default('pendiente');
            // De qué fase salen sus participantes (la consolación, el cuadro).
            $tabla->foreignId('fase_origen_id')->nullable()->constrained('fases')->nullOnDelete();
            // Para repetir el sorteo.
            $tabla->integer('semilla_sorteo')->nullable();
            $tabla->timestamp('cerrada_en')->nullable();
            $tabla->timestamps();
            $tabla->softDeletes();

            $tabla->unique(['categoria_id', 'orden']);
        });

        Schema::create('grupos', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('fase_id')->constrained('fases')->cascadeOnDelete();
            // «A», «B»…
            $tabla->string('nombre', 20);
            $tabla->unsignedSmallInteger('orden')->default(1);
            $tabla->timestamps();
        });

        Schema::create('grupo_participantes', function (Blueprint $tabla) {
            $tabla->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $tabla->foreignId('participante_id')->constrained('participantes')->cascadeOnDelete();
            $tabla->unsignedSmallInteger('posicion_sorteo')->default(1);

            // Un participante en un solo grupo por fase lo garantiza la acción de sorteo.
            $tabla->unique(['grupo_id', 'participante_id']);
        });

        Schema::create('partidos', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('fase_id')->constrained('fases')->cascadeOnDelete();
            $tabla->foreignId('grupo_id')->nullable()->constrained('grupos')->cascadeOnDelete();
            // Orden en el round robin.
            $tabla->unsignedSmallInteger('jornada')->nullable();
            // 1 = primera ronda del cuadro; la final es la última.
            $tabla->unsignedSmallInteger('ronda')->nullable();
            // Hueco dentro de la ronda, desde 1.
            $tabla->unsignedSmallInteger('posicion')->nullable();
            $tabla->boolean('es_tercer_puesto')->default(false);
            $tabla->foreignId('local_id')->nullable()->constrained('participantes')->nullOnDelete();
            $tabla->foreignId('visitante_id')->nullable()->constrained('participantes')->nullOnDelete();
            // DTO Origen: {tipo, referencia, posicion}.
            $tabla->json('origen_local')->nullable();
            $tabla->json('origen_visitante')->nullable();
            // Formato propio de la ronda.
            $tabla->foreignId('formato_partido_id')->nullable()->constrained('formatos_partido')->nullOnDelete();
            $tabla->foreignId('pista_id')->nullable()->constrained('pistas')->nullOnDelete();
            // Una franja aloja como máximo un partido.
            $tabla->foreignId('franja_id')->nullable()->unique()->constrained('franjas')->nullOnDelete();
            // EstadoPartido.
            $tabla->string('estado', 24)->default('pendiente');
            // ResultadoEspecial.
            $tabla->string('especial', 16)->nullable();
            $tabla->foreignId('ganador_id')->nullable()->constrained('participantes')->nullOnDelete();
            $tabla->foreignId('registrado_por_jugador_id')->nullable()->constrained('jugadores')->nullOnDelete();
            $tabla->timestamp('registrado_en')->nullable();
            $tabla->timestamp('validacion_vence_en')->nullable();
            $tabla->timestamp('validado_en')->nullable();
            $tabla->foreignId('validado_por')->nullable()->constrained('users')->nullOnDelete();
            $tabla->string('notas')->nullable();
            $tabla->timestamps();
            $tabla->softDeletes();

            $tabla->unique(['fase_id', 'ronda', 'posicion']);
            $tabla->index(['fase_id', 'estado']);
            $tabla->index(['pista_id', 'franja_id']);
        });

        Schema::create('sets', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('partido_id')->constrained('partidos')->cascadeOnDelete();
            $tabla->unsignedTinyInteger('numero');
            $tabla->unsignedTinyInteger('juegos_local');
            $tabla->unsignedTinyInteger('juegos_visitante');
            $tabla->unsignedTinyInteger('tie_break_local')->nullable();
            $tabla->unsignedTinyInteger('tie_break_visitante')->nullable();
            $tabla->boolean('es_super_tie_break')->default(false);

            $tabla->unique(['partido_id', 'numero']);
        });

        Schema::create('clasificaciones', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('fase_id')->constrained('fases')->cascadeOnDelete();
            $tabla->foreignId('grupo_id')->nullable()->constrained('grupos')->cascadeOnDelete();
            $tabla->foreignId('participante_id')->constrained('participantes')->cascadeOnDelete();
            $tabla->unsignedSmallInteger('posicion')->default(0);
            $tabla->smallInteger('puntos')->default(0);
            $tabla->unsignedSmallInteger('pj')->default(0);
            $tabla->unsignedSmallInteger('pg')->default(0);
            $tabla->unsignedSmallInteger('pp')->default(0);
            $tabla->unsignedSmallInteger('sets_favor')->default(0);
            $tabla->unsignedSmallInteger('sets_contra')->default(0);
            $tabla->unsignedSmallInteger('juegos_favor')->default(0);
            $tabla->unsignedSmallInteger('juegos_contra')->default(0);
            // CriterioDeDesempate que separó esta línea de la siguiente.
            $tabla->string('desempate_aplicado', 40)->nullable();
            // Número fijado la primera vez que hizo falta: el recálculo no lo cambia.
            $tabla->integer('sorteo_desempate')->nullable();
            // «1º A», «mejor tercero».
            $tabla->string('clasificado_como', 40)->nullable();
            $tabla->timestamp('calculada_en')->nullable();

            $tabla->unique(['fase_id', 'grupo_id', 'participante_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clasificaciones');
        Schema::dropIfExists('sets');
        Schema::dropIfExists('partidos');
        Schema::dropIfExists('grupo_participantes');
        Schema::dropIfExists('grupos');
        Schema::dropIfExists('fases');
    }
};
