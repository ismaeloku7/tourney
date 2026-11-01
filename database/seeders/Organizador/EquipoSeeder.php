<?php

namespace Database\Seeders\Organizador;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Una cuenta por rol, como en CEAS: propietario@, administrador@ y
 * arbitro@example.com, con la clave `password`. El propietario lo crea el
 * alta del organizador; aquí se completa el resto.
 */
class EquipoSeeder extends Seeder
{
    public function run(): void
    {
        $cuentas = [
            ['propietario@example.com', 'Propietario', 'Demo', Rol::Propietario],
            ['administrador@example.com', 'Administradora', 'De Torneo', Rol::Administrador],
            ['arbitro@example.com', 'Árbitro', 'De Mesa', Rol::Arbitro],
        ];

        foreach ($cuentas as [$email, $nombre, $apellidos, $rol]) {
            User::query()->firstOrCreate(['email' => $email], [
                'nombre' => $nombre,
                'apellidos' => $apellidos,
                'rol' => $rol,
                'activo' => true,
                'email_verified_at' => now(),
                'password' => 'password',
            ]);
        }
    }
}
