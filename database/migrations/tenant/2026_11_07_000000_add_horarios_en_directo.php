<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los horarios en directo: la hora prevista de cada partido al planificar
 * (para saber cuánto se retrasa su pista) y cuándo se avisó a sus parejas
 * de que son las siguientes y de que pasen a calentar. Las horas pasan a
 * ser estimadas y se mueven durante el día: un partido que acabó puede
 * tener la misma hora de inicio que el que entra después en su pista, así
 * que deja de ser única por pista (los solapes los evitan el planificador
 * y «Hora y pista»).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Cada paso se salta si ya está hecho: en MySQL los cambios de tablas no se deshacen si algo falla a medias.
        if (! Schema::hasColumn('franjas', 'inicio_previsto')) {
            Schema::table('franjas', function (Blueprint $tabla) {
                $tabla->dateTime('inicio_previsto')->nullable()->after('fin');
            });
        }

        // En MySQL la clave ajena de pista_id se apoya en el índice único: primero el índice normal, luego fuera el único.
        if (! Schema::hasIndex('franjas', 'franjas_pista_id_inicio_index')) {
            Schema::table('franjas', function (Blueprint $tabla) {
                $tabla->index(['pista_id', 'inicio']);
            });
        }

        if (Schema::hasIndex('franjas', 'franjas_pista_id_inicio_unique')) {
            Schema::table('franjas', function (Blueprint $tabla) {
                $tabla->dropUnique(['pista_id', 'inicio']);
            });
        }

        if (! Schema::hasColumn('partidos', 'aviso_siguientes_en')) {
            Schema::table('partidos', function (Blueprint $tabla) {
                $tabla->dateTime('aviso_siguientes_en')->nullable()->after('aviso_proximo_en');
            });
        }

        if (! Schema::hasColumn('partidos', 'aviso_calentar_en')) {
            Schema::table('partidos', function (Blueprint $tabla) {
                $tabla->dateTime('aviso_calentar_en')->nullable()->after('aviso_siguientes_en');
            });
        }
    }

    public function down(): void
    {
        Schema::table('franjas', function (Blueprint $tabla) {
            $tabla->unique(['pista_id', 'inicio']);
        });

        Schema::table('franjas', function (Blueprint $tabla) {
            $tabla->dropIndex(['pista_id', 'inicio']);
            $tabla->dropColumn('inicio_previsto');
        });

        Schema::table('partidos', function (Blueprint $tabla) {
            $tabla->dropColumn(['aviso_siguientes_en', 'aviso_calentar_en']);
        });
    }
};
