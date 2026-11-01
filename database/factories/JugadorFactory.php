<?php

namespace Database\Factories;

use App\Enums\Genero;
use App\Models\Jugador;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Jugador>
 */
class JugadorFactory extends Factory
{
    public function definition(): array
    {
        $genero = fake()->randomElement([Genero::Masculino, Genero::Femenino]);

        return [
            'nombre' => $genero === Genero::Masculino ? fake()->firstNameMale() : fake()->firstNameFemale(),
            'apellidos' => fake()->lastName().' '.fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'telefono' => fake()->numerify('6########'),
            'genero' => $genero,
            'nivel_declarado' => fake()->randomElement(['3ª', '4ª', '5ª']),
            'consentimiento_en' => now(),
        ];
    }

    public function conAlias(string $alias): static
    {
        return $this->state(['alias' => $alias]);
    }
}
