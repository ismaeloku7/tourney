<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El formato «Dos sets + super tie-break» (dos sets a 6 con tie-break y,
 * si van 1-1, un super tie-break a 10 en vez del tercer set) en todos los
 * organizadores. El que ya venía de serie con otro nombre se renombra; si
 * no hay ninguno, se crea. Repetible.
 */
return new class extends Migration
{
    private const NOMBRE = 'Dos sets + super tie-break';

    private const NOMBRE_ANTIGUO = 'Al mejor de 3 con super tie-break';

    public function up(): void
    {
        // Solo los que están en uso: uno borrado (en la papelera) no cuenta.
        $formatos = DB::table('formatos_partido')->whereNull('deleted_at');

        if ((clone $formatos)->where('nombre', self::NOMBRE)->exists()) {
            return;
        }

        if ((clone $formatos)->where('nombre', self::NOMBRE_ANTIGUO)->exists()) {
            (clone $formatos)->where('nombre', self::NOMBRE_ANTIGUO)->update(['nombre' => self::NOMBRE, 'updated_at' => now()]);

            return;
        }

        DB::table('formatos_partido')->insert([
            'nombre' => self::NOMBRE,
            'sets' => 3,
            'juegos_por_set' => 6,
            'diferencia_para_ganar_set' => 2,
            'tie_break_en' => 6,
            'tie_break_puntos' => 7,
            'super_tie_break_final' => true,
            'super_tie_break_puntos' => 10,
            'punto_de_oro' => true,
            'duracion_estimada' => 60,
            'es_predeterminado' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('formatos_partido')->whereNull('deleted_at')->where('nombre', self::NOMBRE)->update(['nombre' => self::NOMBRE_ANTIGUO]);
    }
};
