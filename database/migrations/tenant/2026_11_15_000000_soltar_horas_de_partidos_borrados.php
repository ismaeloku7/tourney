<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Las horas (franjas) que se quedaron reservadas a partidos ya borrados al
 * rehacer grupos o partidos: se sueltan, para que no ocupen pista. Los
 * bloqueos de pista se quedan. Repetible.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('franjas')
            ->where('bloqueada', false)
            ->whereNotExists(fn ($q) => $q->from('partidos')->whereColumn('partidos.franja_id', 'franjas.id'))
            ->delete();
    }

    public function down(): void {}
};
