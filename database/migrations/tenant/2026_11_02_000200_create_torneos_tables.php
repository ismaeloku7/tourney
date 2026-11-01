<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los torneos con su equipo (administradores y árbitros), sus pistas con
 * la disponibilidad de cada una, las franjas generadas y sus categorías.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('torneos', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('nombre', 160);
            // Para /torneos/{slug} en la web del organizador.
            $tabla->string('slug', 80)->unique();
            $tabla->string('deporte', 20)->default('padel');
            $tabla->foreignId('sede_id')->nullable()->constrained('sedes')->nullOnDelete();
            $tabla->string('direccion')->nullable();
            $tabla->date('fecha_inicio');
            $tabla->date('fecha_fin');
            $tabla->dateTime('inscripcion_desde')->nullable();
            $tabla->dateTime('inscripcion_hasta')->nullable();
            // EstadoTorneo.
            $tabla->string('estado', 24)->default('borrador')->index();
            $tabla->boolean('listado_en_plataforma')->default(false);
            // ResultadosPorJugadores.
            $tabla->string('resultados_por_jugadores', 16)->default('desactivado');
            $tabla->unsignedSmallInteger('minutos_para_validar')->default(30);
            $tabla->boolean('privacidad_alias')->default(false);
            $tabla->string('cartel_path')->nullable();
            $tabla->text('normativa')->nullable();
            $tabla->string('contacto')->nullable();
            // Restricciones del planificador.
            $tabla->unsignedSmallInteger('descanso_minimo_minutos')->default(30);
            $tabla->unsignedTinyInteger('margen_entre_partidos_minutos')->default(0);
            // Definición de los campos extra de la inscripción (fase 3).
            $tabla->json('campos_extra')->nullable();
            $tabla->timestamp('publicado_en')->nullable();
            $tabla->timestamp('horarios_publicados_en')->nullable();
            $tabla->timestamp('finalizado_en')->nullable();
            $tabla->timestamp('archivado_en')->nullable();
            $tabla->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $tabla->timestamps();
            $tabla->softDeletes();

            $tabla->index(['estado', 'fecha_inicio']);
        });

        Schema::create('torneo_administradores', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('torneo_id')->constrained('torneos')->cascadeOnDelete();
            $tabla->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $tabla->timestamps();

            $tabla->unique(['torneo_id', 'user_id']);
        });

        Schema::create('torneo_arbitros', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('torneo_id')->constrained('torneos')->cascadeOnDelete();
            $tabla->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Nulo: todas las pistas del torneo.
            $tabla->foreignId('pista_id')->nullable()->constrained('pistas')->cascadeOnDelete();
            $tabla->timestamps();

            $tabla->unique(['torneo_id', 'user_id', 'pista_id']);
        });

        Schema::create('torneo_pistas', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('torneo_id')->constrained('torneos')->cascadeOnDelete();
            $tabla->foreignId('pista_id')->constrained('pistas')->cascadeOnDelete();
            $tabla->unsignedSmallInteger('orden')->default(0);
            // Lista de {fecha, desde, hasta} (DTO DisponibilidadDePista).
            $tabla->json('disponibilidad');
            $tabla->timestamps();

            $tabla->unique(['torneo_id', 'pista_id']);
        });

        Schema::create('franjas', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('torneo_id')->constrained('torneos')->cascadeOnDelete();
            $tabla->foreignId('pista_id')->constrained('pistas')->cascadeOnDelete();
            $tabla->dateTime('inicio');
            $tabla->dateTime('fin');
            // La reserva el organizador a mano: el planificador no la usa.
            $tabla->boolean('bloqueada')->default(false);
            $tabla->timestamps();

            $tabla->unique(['pista_id', 'inicio']);
            $tabla->index(['torneo_id', 'inicio']);
        });

        Schema::create('categorias', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('torneo_id')->constrained('torneos')->cascadeOnDelete();
            $tabla->string('nombre', 80);
            // Modalidad: la v1 solo usa parejas.
            $tabla->string('modalidad', 12)->default('parejas');
            // Genero.
            $tabla->string('genero', 12)->default('abierto');
            $tabla->string('nivel', 40)->nullable();
            $tabla->unsignedSmallInteger('cupo')->nullable();
            $tabla->unsignedSmallInteger('minimo_participantes')->default(0);
            $tabla->decimal('precio', 8, 2)->nullable();
            $tabla->foreignId('formato_partido_id')->nullable()->constrained('formatos_partido')->nullOnDelete();
            // También es la prioridad del planificador.
            $tabla->unsignedSmallInteger('orden')->default(0);
            $tabla->timestamps();
            $tabla->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categorias');
        Schema::dropIfExists('franjas');
        Schema::dropIfExists('torneo_pistas');
        Schema::dropIfExists('torneo_arbitros');
        Schema::dropIfExists('torneo_administradores');
        Schema::dropIfExists('torneos');
    }
};
