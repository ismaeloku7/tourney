<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los enlaces de acceso del jugador: se guarda el hash del token, valen
 * quince minutos y una sola vez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enlaces_magicos', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('jugador_id')->constrained('jugadores')->cascadeOnDelete();
            $tabla->char('token', 64)->unique();
            $tabla->timestamp('expira_en');
            $tabla->timestamp('usado_en')->nullable();
            $tabla->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enlaces_magicos');
    }
};
