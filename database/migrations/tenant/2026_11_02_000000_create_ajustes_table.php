<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los ajustes del organizador (marca, zona horaria, textos legales), una
 * fila por clave del enum Ajuste. En su base: el ZIP de baja se lo lleva
 * todo y el manifiesto PWA ya necesita esta base para el logo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ajustes', function (Blueprint $tabla) {
            $tabla->string('clave', 40)->primary();
            $tabla->json('valor')->nullable();
            $tabla->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ajustes');
    }
};
