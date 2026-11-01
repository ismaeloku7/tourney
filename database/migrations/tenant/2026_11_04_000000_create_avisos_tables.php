<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los avisos a jugadores y público: los dispositivos con push (de un
 * jugador, de alguien del equipo o de quien sigue un torneo sin cuenta),
 * los torneos que sigue cada dispositivo, el historial de avisos del
 * portal (`notifications` de Laravel), los avisos libres de la
 * organización y la marca de «próximo partido avisado».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_suscripciones', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->text('endpoint');
            $tabla->char('endpoint_hash', 64)->unique();
            $tabla->string('clave_p256dh', 255);
            $tabla->string('clave_auth', 255);
            $tabla->foreignId('jugador_id')->nullable()->constrained('jugadores')->cascadeOnDelete();
            $tabla->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $tabla->string('dispositivo', 120)->nullable();
            $tabla->timestamp('ultimo_envio')->nullable();
            $tabla->timestamps();
        });

        Schema::create('push_suscripcion_torneos', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('suscripcion_id')->constrained('push_suscripciones')->cascadeOnDelete();
            $tabla->foreignId('torneo_id')->constrained('torneos')->cascadeOnDelete();
            $tabla->unique(['suscripcion_id', 'torneo_id']);
        });

        Schema::create('notifications', function (Blueprint $tabla) {
            $tabla->uuid('id')->primary();
            $tabla->string('type');
            $tabla->morphs('notifiable');
            $tabla->text('data');
            $tabla->timestamp('read_at')->nullable();
            $tabla->timestamps();
        });

        Schema::create('comunicaciones', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('torneo_id')->constrained('torneos')->cascadeOnDelete();
            $tabla->foreignId('categoria_id')->nullable()->constrained('categorias')->nullOnDelete();
            $tabla->string('titulo', 120);
            $tabla->text('cuerpo');
            $tabla->json('canales');
            $tabla->foreignId('enviada_por')->nullable()->constrained('users')->nullOnDelete();
            $tabla->timestamp('enviada_en')->nullable();
            $tabla->unsignedInteger('destinatarios')->default(0);
            $tabla->timestamps();
        });

        Schema::table('partidos', function (Blueprint $tabla) {
            $tabla->timestamp('aviso_proximo_en')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('partidos', function (Blueprint $tabla) {
            $tabla->dropColumn('aviso_proximo_en');
        });
        Schema::dropIfExists('comunicaciones');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('push_suscripcion_torneos');
        Schema::dropIfExists('push_suscripciones');
    }
};
