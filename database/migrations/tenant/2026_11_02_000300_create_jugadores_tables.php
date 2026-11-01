<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los jugadores del organizador (guard `jugador`), los participantes que
 * compiten (la pareja, en pádel) con sus jugadores, las inscripciones y
 * las no disponibilidades que cada jugador declara para un torneo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jugadores', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('nombre', 80);
            $tabla->string('apellidos', 120);
            $tabla->string('email', 190)->nullable()->unique();
            $tabla->string('telefono', 30)->nullable();
            $tabla->date('fecha_nacimiento')->nullable();
            $tabla->string('genero', 12)->nullable();
            $tabla->string('nivel_declarado', 40)->nullable();
            // Lo que se enseña en público si el torneo activa la privacidad.
            $tabla->string('alias', 60)->nullable();
            $tabla->string('password')->nullable();
            $tabla->rememberToken();
            $tabla->timestamp('email_verified_at')->nullable();
            $tabla->timestamp('consentimiento_en')->nullable();
            // Qué avisos quiere recibir.
            $tabla->json('avisos')->nullable();
            // Reservado para la cuenta global de jugador (fase 2 o posterior).
            $tabla->string('identificador_plataforma')->nullable();
            // Derecho de supresión: se vacían nombre, email y teléfono; sus
            // participantes y resultados siguen.
            $tabla->timestamp('anonimizado_en')->nullable();
            // Duplicado fusionado en otro jugador.
            $tabla->foreignId('fusionado_en_id')->nullable()->constrained('jugadores')->nullOnDelete();
            $tabla->timestamp('ultimo_acceso')->nullable();
            $tabla->timestamps();
            $tabla->softDeletes();
        });

        Schema::create('participantes', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('categoria_id')->constrained('categorias')->cascadeOnDelete();
            // Lo visible: «García / López».
            $tabla->string('nombre', 160);
            // 1 = primera cabeza de serie.
            $tabla->unsignedSmallInteger('cabeza_de_serie')->nullable();
            // EstadoParticipante.
            $tabla->string('estado', 12)->default('activo');
            $tabla->timestamps();
            $tabla->softDeletes();
        });

        Schema::create('participante_jugadores', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('participante_id')->constrained('participantes')->cascadeOnDelete();
            $tabla->foreignId('jugador_id')->constrained('jugadores')->cascadeOnDelete();
            // 1 o 2 en pádel; más con equipos.
            $tabla->unsignedTinyInteger('orden')->default(1);
            // Nulo mientras el compañero invitado no confirma.
            $tabla->timestamp('confirmado_en')->nullable();
            $tabla->char('invitacion_token', 64)->nullable();
            $tabla->timestamp('invitacion_expira_en')->nullable();
            $tabla->timestamps();

            $tabla->unique(['participante_id', 'jugador_id']);
            $tabla->index('jugador_id');
        });

        Schema::create('inscripciones', function (Blueprint $tabla) {
            $tabla->id();
            // Se repite respecto a participantes porque es la tabla que
            // filtra el panel; la acción que inscribe garantiza que coinciden.
            $tabla->foreignId('categoria_id')->constrained('categorias')->cascadeOnDelete();
            $tabla->foreignId('participante_id')->unique()->constrained('participantes')->cascadeOnDelete();
            // EstadoInscripcion.
            $tabla->string('estado', 16)->default('pendiente')->index();
            $tabla->unsignedSmallInteger('posicion_espera')->nullable();
            $tabla->timestamp('pago_marcado_en')->nullable();
            $tabla->string('pago_notas')->nullable();
            $tabla->text('notas')->nullable();
            // OrigenInscripcion.
            $tabla->string('origen', 12)->default('manual');
            $tabla->json('respuestas_extra')->nullable();
            $tabla->timestamp('confirmada_en')->nullable();
            $tabla->timestamp('baja_en')->nullable();
            $tabla->foreignId('registrada_por')->nullable()->constrained('users')->nullOnDelete();
            $tabla->timestamps();
            $tabla->softDeletes();
        });

        Schema::create('disponibilidades', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('jugador_id')->constrained('jugadores')->cascadeOnDelete();
            $tabla->foreignId('torneo_id')->constrained('torneos')->cascadeOnDelete();
            // Intervalo en el que el jugador NO puede jugar.
            $tabla->dateTime('desde');
            $tabla->dateTime('hasta');
            $tabla->timestamps();

            $tabla->index(['torneo_id', 'jugador_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disponibilidades');
        Schema::dropIfExists('inscripciones');
        Schema::dropIfExists('participante_jugadores');
        Schema::dropIfExists('participantes');
        Schema::dropIfExists('jugadores');
    }
};
