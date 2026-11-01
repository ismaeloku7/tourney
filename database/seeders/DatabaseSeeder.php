<?php

namespace Database\Seeders;

use App\Actions\Plataforma\CrearOrganizador;
use App\Actions\Plataforma\DatosDeOrganizadorNuevo;
use App\Models\Organizador;
use App\Models\Plan;
use App\Models\UsuarioPlataforma;
use App\Tenancy\OrganizadorActual;
use Database\Seeders\Organizador\OrganizadorDePruebasSeeder;
use Illuminate\Database\Seeder;

/**
 * Datos del entorno de pruebas: los planes, un usuario de plataforma y el
 * organizador `demo` con todo lo que hace falta para recorrer la
 * aplicación (Database\Seeders\Organizador). Nada de aquí es de un
 * organizador real. Se puede volver a sembrar sin duplicar.
 *
 * Sin `WithoutModelEvents`: crear un organizador debe crear su base.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PlanesSeeder::class);

        // Plataforma: http://saas.localhost:8000/plataforma (fase 4).
        UsuarioPlataforma::query()->where('email', 'admin@example.com')->exists()
            || UsuarioPlataforma::factory()->create(['nombre' => 'Administración de la plataforma', 'email' => 'admin@example.com']);

        // En local se abre en localhost gracias a ORGANIZADORES_HOSTS_DESARROLLO.
        // Crear el organizador crea su base y la migra (stancl/tenancy).
        $demo = Organizador::query()->find('demo') ?? app(CrearOrganizador::class)->handle(new DatosDeOrganizadorNuevo(
            slug: 'demo',
            nombre: 'Club Demo',
            responsableNombre: 'Propietario Demo',
            responsableEmail: 'propietario@example.com',
            responsablePassword: 'password',
            plan: Plan::CLUB,
        ));

        app(OrganizadorActual::class)->ejecutarComo($demo, fn () => $this->call(OrganizadorDePruebasSeeder::class));
    }
}
