<?php

namespace Database\Factories;

use App\Models\Franja;
use App\Models\Pista;
use App\Models\Torneo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Franja>
 */
class FranjaFactory extends Factory
{
    public function definition(): array
    {
        $inicio = now()->addDays(7)->setTime(9, 0);

        return [
            'torneo_id' => Torneo::factory(),
            'pista_id' => Pista::factory(),
            'inicio' => $inicio,
            'fin' => $inicio->addMinutes(75),
            'bloqueada' => false,
        ];
    }
}
