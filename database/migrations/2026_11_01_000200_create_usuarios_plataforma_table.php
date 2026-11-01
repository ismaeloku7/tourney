<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los usuarios de la plataforma, en la base central. No son equipo de
 * ningún organizador ni entran en sus paneles: gestionan organizadores,
 * planes y soporte, no su contenido.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuarios_plataforma', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('nombre', 120);
            $tabla->string('email', 190)->unique();
            $tabla->string('password');
            $tabla->boolean('activo')->default(true);
            $tabla->rememberToken();
            $tabla->timestamp('ultimo_acceso')->nullable();
            $tabla->timestamps();
        });

        Schema::create('password_reset_tokens_plataforma', function (Blueprint $tabla) {
            $tabla->string('email', 190)->primary();
            $tabla->string('token');
            $tabla->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens_plataforma');
        Schema::dropIfExists('usuarios_plataforma');
    }
};
