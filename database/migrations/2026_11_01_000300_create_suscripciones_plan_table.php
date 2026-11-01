<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Histórico de planes de cada organizador: cambiar de plan cierra la fila
 * abierta (`hasta`) y abre otra. `organizadores.plan_id` es la copia de la
 * fila abierta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suscripciones_plan', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('organizador_id', 40);
            $tabla->foreignId('plan_id')->constrained('planes')->restrictOnDelete();
            $tabla->date('desde');
            $tabla->date('hasta')->nullable();
            $tabla->text('notas')->nullable();
            $tabla->foreignId('cambiado_por')->nullable()->constrained('usuarios_plataforma')->nullOnDelete();
            $tabla->timestamps();

            $tabla->foreign('organizador_id')->references('id')->on('organizadores')->cascadeOnUpdate()->cascadeOnDelete();
            $tabla->index(['organizador_id', 'hasta']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suscripciones_plan');
    }
};
