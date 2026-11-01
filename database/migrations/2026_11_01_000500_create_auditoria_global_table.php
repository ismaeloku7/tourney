<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que la plataforma hace sobre los organizadores: activar, suspender,
 * cambiar de plan, entrar como propietario, programar la baja, verificar
 * un dominio… Sin `updated_at`: una fila no se edita.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditoria_global', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('usuario_plataforma_id')->nullable()->constrained('usuarios_plataforma')->nullOnDelete();
            $tabla->string('organizador_id', 40)->nullable();
            $tabla->string('accion', 60);
            $tabla->json('detalles')->nullable();
            $tabla->timestamp('created_at')->index();

            $tabla->foreign('organizador_id')->references('id')->on('organizadores')->cascadeOnUpdate()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditoria_global');
    }
};
