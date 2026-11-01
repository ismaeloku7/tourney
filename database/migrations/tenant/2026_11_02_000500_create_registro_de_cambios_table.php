<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El antes y el después de cada operación sobre sorteos, cuadros,
 * horarios y resultados, con quién la hizo (alguien del equipo o un
 * jugador). Se escribe dentro de la transacción de la acción.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registro_de_cambios', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $tabla->foreignId('jugador_id')->nullable()->constrained('jugadores')->nullOnDelete();
            $tabla->string('accion', 40);
            $tabla->string('sujeto_type', 80);
            $tabla->unsignedBigInteger('sujeto_id');
            $tabla->json('antes')->nullable();
            $tabla->json('despues')->nullable();
            $tabla->timestamp('created_at')->index();

            $tabla->index(['sujeto_type', 'sujeto_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registro_de_cambios');
    }
};
