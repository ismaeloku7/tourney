<?php

namespace Database\Factories;

use App\Models\Pista;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pista>
 */
class PistaFactory extends Factory
{
    private static int $contador = 0;

    public function definition(): array
    {
        self::$contador++;

        return [
            'nombre' => 'Pista '.self::$contador,
            'orden' => self::$contador,
            'activa' => true,
        ];
    }
}
