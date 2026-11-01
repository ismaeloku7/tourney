<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Los tres planes del briefing. Se puede volver a sembrar: actualiza por
 * clave sin duplicar. Los precios son orientativos.
 */
class PlanesSeeder extends Seeder
{
    public function run(): void
    {
        $planes = [
            [Plan::GRATIS, 'Gratis', null, 1, 16, 2, false, false, false, false, false],
            [Plan::CLUB, 'Club', '19.00', 3, 64, 6, true, true, true, false, false],
            [Plan::ORGANIZADOR, 'Organizador', '49.00', null, 200, null, true, true, true, true, true],
        ];

        foreach ($planes as $orden => [$clave, $nombre, $precio, $torneos, $parejas, $categorias, $sinMarca, $push, $exportaciones, $dominio, $varios]) {
            Plan::query()->updateOrCreate(['clave' => $clave], [
                'nombre' => $nombre,
                'precio_mensual' => $precio,
                'torneos_activos' => $torneos,
                'parejas_por_torneo' => $parejas,
                'categorias_por_torneo' => $categorias,
                'sin_marca' => $sinMarca,
                'push_ilimitado' => $push,
                'exportaciones' => $exportaciones,
                'dominio_propio' => $dominio,
                'varios_administradores' => $varios,
                'orden' => $orden,
                'activo' => true,
            ]);
        }
    }
}
