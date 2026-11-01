<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Métricas de uso de cada organizador, en la central, para que el panel
 * de plataforma las agregue sin abrir ninguna base de organizador. No se
 * usan para aplicar límites de plan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eventos_uso', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('organizador_id', 40);
            // TipoDeEventoDeUso.
            $tabla->string('tipo', 40)->index();
            // 1 por defecto; los push agrupados anotan cuántos.
            $tabla->unsignedInteger('cantidad')->default(1);
            // Id del torneo en la base del organizador, si aplica.
            $tabla->string('referencia')->nullable();
            $tabla->timestamp('ocurrido_en');

            $tabla->foreign('organizador_id')->references('id')->on('organizadores')->cascadeOnUpdate()->cascadeOnDelete();
            $tabla->index(['organizador_id', 'tipo', 'ocurrido_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_uso');
    }
};
