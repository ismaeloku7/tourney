<?php

namespace Database\Seeders\Organizador;

use App\Models\Pista;
use App\Models\Sede;
use Illuminate\Database\Seeder;

/**
 * Una sede con cuatro pistas. Los formatos de partido los pone
 * BaseDelOrganizadorSeeder.
 */
class InstalacionesSeeder extends Seeder
{
    public function run(): void
    {
        $sede = Sede::query()->firstOrCreate(['nombre' => 'Club Demo Pádel'], [
            'direccion' => 'Calle de la Raqueta, 1, Valencia',
            'orden' => 1,
        ]);

        foreach ([1, 2, 3, 4] as $numero) {
            Pista::query()->firstOrCreate(['sede_id' => $sede->id, 'nombre' => "Pista {$numero}"], ['orden' => $numero, 'activa' => true]);
        }
    }
}
