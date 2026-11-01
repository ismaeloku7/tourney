<?php

namespace Database\Seeders\Organizador;

use App\Enums\Genero;
use App\Models\Jugador;
use Illuminate\Database\Seeder;

/**
 * 48 jugadores inventados, uno de ellos jugador@example.com con la clave
 * `password` (para el portal del jugador). Se identifican por correo:
 * repetir no duplica.
 */
class JugadoresSeeder extends Seeder
{
    public const TOTAL = 48;

    public function run(): void
    {
        Jugador::query()->firstOrCreate(['email' => 'jugador@example.com'], [
            'nombre' => 'Jugador',
            'apellidos' => 'De Pruebas',
            'telefono' => '600000000',
            'genero' => Genero::Masculino,
            'nivel_declarado' => '4ª',
            'alias' => 'El Jugador',
            'password' => 'password',
            'email_verified_at' => now(),
            'consentimiento_en' => now(),
        ]);

        for ($i = 1; $i < self::TOTAL; $i++) {
            $email = sprintf('jugador%02d@example.com', $i);

            if (Jugador::query()->where('email', $email)->doesntExist()) {
                Jugador::factory()->create(['email' => $email, 'genero' => $i % 2 === 0 ? Genero::Femenino : Genero::Masculino]);
            }
        }
    }
}
