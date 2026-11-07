<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los enlaces de acceso directo de cada pareja: con él entra uno de sus
 * jugadores en su portal sin correo ni contraseña, y no caduca (hasta que
 * la organización lo borra). El token se guarda cifrado, para poder
 * enseñarlo de nuevo, y su hash, para encontrarlo al entrar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('accesos_directos')) {
            return;
        }

        Schema::create('accesos_directos', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('inscripcion_id')->unique()->constrained('inscripciones')->cascadeOnDelete();
            $tabla->foreignId('jugador_id')->constrained('jugadores')->cascadeOnDelete();
            $tabla->text('token');
            $tabla->char('token_hash', 64)->unique();
            $tabla->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $tabla->timestamp('ultimo_uso_en')->nullable();
            $tabla->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accesos_directos');
    }
};
