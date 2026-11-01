<?php

namespace Database\Factories;

use App\Models\Fase;
use App\Models\Grupo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Grupo>
 */
class GrupoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'fase_id' => Fase::factory(),
            'nombre' => 'A',
            'orden' => 1,
        ];
    }
}
