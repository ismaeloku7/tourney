<?php

namespace Database\Seeders\Organizador;

use Illuminate\Database\Seeder;

/**
 * Todo lo del organizador `demo`: equipo, instalaciones, jugadores y
 * torneos (estos últimos llegan con las acciones de competición). Se
 * ejecuta con `demo` como organizador actual. Repetible.
 */
class OrganizadorDePruebasSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            BaseDelOrganizadorSeeder::class,
            EquipoSeeder::class,
            InstalacionesSeeder::class,
            JugadoresSeeder::class,
        ]);
    }
}
