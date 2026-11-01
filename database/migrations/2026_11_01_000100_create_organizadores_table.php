<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los organizadores (los «tenants» de stancl/tenancy) y sus dominios, en
 * la base central. Cada organizador tiene además su propia base,
 * `organizador_{id}`. Aquí vive solo su identidad: slug, nombre, estado,
 * plan y responsable. La marca y los textos legales van en los ajustes
 * de su base.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizadores', function (Blueprint $tabla) {
            // El slug (`demo`): nombre de la base y subdominio.
            $tabla->string('id', 40)->primary();
            $tabla->string('nombre', 120);
            // pendiente, activo, suspendido o baja (EstadoOrganizador).
            $tabla->string('estado', 20)->default('pendiente')->index();
            // Plan actual; el histórico va en suscripciones_plan.
            $tabla->foreignId('plan_id')->constrained('planes')->restrictOnDelete();
            // Se copian al usuario propietario al crear la base.
            $tabla->string('responsable_nombre', 120);
            $tabla->string('responsable_email', 190);
            // Hash bcrypt hasta crear al propietario en la base del organizador.
            $tabla->string('responsable_password')->nullable();
            // Hash del token del correo de verificación del alta.
            $tabla->char('verificacion_token', 64)->nullable();
            $tabla->timestamp('verificacion_expira_en')->nullable();
            $tabla->timestamp('verificado_en')->nullable();
            $tabla->timestamp('suspendido_en')->nullable();
            $tabla->string('suspension_motivo')->nullable();
            // El borrado de la base se hace 30 días después.
            $tabla->timestamp('baja_programada_en')->nullable();
            // ZIP con sus datos, generado antes de la baja.
            $tabla->string('exportacion_path')->nullable();
            // Lo anota el panel del organizador.
            $tabla->timestamp('ultimo_acceso')->nullable();
            // Columna de atributos libres de stancl/tenancy.
            $tabla->json('data')->nullable();
            $tabla->timestamps();
        });

        Schema::create('domains', function (Blueprint $tabla) {
            $tabla->increments('id');
            // Un subdominio del SaaS se guarda sin el dominio central
            // (`demo`); un dominio propio, entero (`tourney.clubceas.es`).
            $tabla->string('domain')->unique();
            $tabla->string('tenant_id', 40);
            // Verificación por registro DNS TXT y estado del certificado
            // (pendiente, activo, error), para los dominios propios.
            $tabla->string('verificacion_token')->nullable();
            $tabla->timestamp('verificado_en')->nullable();
            $tabla->string('certificado_estado', 20)->nullable();
            $tabla->timestamps();

            $tabla->foreign('tenant_id')->references('id')->on('organizadores')->cascadeOnUpdate()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domains');
        Schema::dropIfExists('organizadores');
    }
};
