<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Datos del entorno de pruebas. Con la base central llegan los planes, el
 * usuario de plataforma y el organizador `demo`; con las migraciones del
 * organizador, su equipo (una cuenta por rol), instalaciones, jugadores y
 * un torneo en cada estado. Se podrá volver a sembrar sin duplicar.
 *
 * Sin `WithoutModelEvents`: crear un organizador debe crear su base.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        //
    }
}
