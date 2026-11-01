<?php

namespace Database\Factories;

use App\Enums\Genero;
use App\Enums\Modalidad;
use App\Models\Categoria;
use App\Models\Torneo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Categoria>
 */
class CategoriaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'torneo_id' => Torneo::factory(),
            'nombre' => fake()->randomElement(['Masculina 3ª', 'Masculina 4ª', 'Femenina 3ª', 'Femenina 4ª', 'Mixta A', 'Mixta B']),
            'modalidad' => Modalidad::Parejas,
            'genero' => Genero::Abierto,
            'cupo' => 16,
            'minimo_participantes' => 4,
            'orden' => 1,
        ];
    }
}
