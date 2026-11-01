<?php

namespace Database\Factories;

use App\Models\Disponibilidad;
use App\Models\Jugador;
use App\Models\Torneo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Disponibilidad>
 */
class DisponibilidadFactory extends Factory
{
    public function definition(): array
    {
        $desde = now()->addDays(7)->setTime(9, 0);

        return [
            'jugador_id' => Jugador::factory(),
            'torneo_id' => Torneo::factory(),
            'desde' => $desde,
            'hasta' => $desde->addHours(3),
        ];
    }
}
