<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    public function definition(): array
    {
        $clave = 'plan'.Str::lower(Str::random(6));

        return [
            'clave' => $clave,
            'nombre' => 'Plan '.ucfirst(substr($clave, 4)),
            'precio_mensual' => null,
            'torneos_activos' => 1,
            'parejas_por_torneo' => 16,
            'categorias_por_torneo' => 2,
            'orden' => 0,
            'activo' => true,
        ];
    }

    public function ilimitado(): static
    {
        return $this->state(['torneos_activos' => null, 'parejas_por_torneo' => null, 'categorias_por_torneo' => null]);
    }
}
