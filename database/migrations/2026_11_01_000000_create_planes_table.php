<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los planes de la plataforma (gratis, club, organizador) con sus límites.
 * Un límite a nulo es «ilimitado». Los precios son informativos: en v1 el
 * cambio de plan lo hace a mano la plataforma.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planes', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('clave', 30)->unique();
            $tabla->string('nombre', 60);
            $tabla->decimal('precio_mensual', 8, 2)->nullable();
            $tabla->unsignedSmallInteger('torneos_activos')->nullable();
            $tabla->unsignedSmallInteger('parejas_por_torneo')->nullable();
            $tabla->unsignedSmallInteger('categorias_por_torneo')->nullable();
            $tabla->boolean('sin_marca')->default(false);
            $tabla->boolean('push_ilimitado')->default(false);
            $tabla->boolean('exportaciones')->default(false);
            $tabla->boolean('dominio_propio')->default(false);
            $tabla->boolean('varios_administradores')->default(false);
            $tabla->unsignedSmallInteger('orden')->default(0);
            $tabla->boolean('activo')->default(true);
            $tabla->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planes');
    }
};
