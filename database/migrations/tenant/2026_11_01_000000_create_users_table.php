<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El equipo del organizador (propietario, administradores de torneo y
 * árbitros) y sus tokens de recuperación de clave. En la base del
 * organizador: el mismo correo puede ser equipo de otro organizador, en
 * otra base. Un invitado se crea sin contraseña y recibe un enlace
 * firmado para ponerla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('nombre', 120);
            $tabla->string('apellidos', 160)->default('');
            $tabla->string('email', 190)->unique();
            $tabla->string('telefono', 30)->nullable();
            // Rol: propietario, administrador o arbitro.
            $tabla->string('rol', 20)->index();
            $tabla->boolean('activo')->default(true);
            $tabla->timestamp('email_verified_at')->nullable();
            $tabla->string('password');
            $tabla->rememberToken();
            $tabla->timestamp('ultimo_acceso')->nullable();
            $tabla->timestamps();
            $tabla->softDeletes();
        });

        Schema::create('password_reset_tokens', function (Blueprint $tabla) {
            $tabla->string('email', 190)->primary();
            $tabla->string('token');
            $tabla->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
