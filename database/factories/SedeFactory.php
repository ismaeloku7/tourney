<?php

namespace Database\Factories;

use App\Models\Sede;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Exige organizador actual.
 *
 * @extends Factory<Sede>
 */
class SedeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nombre' => 'Club '.fake()->lastName(),
            'direccion' => fake()->streetAddress().', '.fake()->city(),
            'orden' => 0,
        ];
    }
}
