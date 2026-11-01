<?php

namespace Database\Factories;

use App\Models\Pista;
use App\Models\Torneo;
use App\Models\TorneoPista;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Por defecto, disponible de 9:00 a 21:00 los días del torneo.
 *
 * @extends Factory<TorneoPista>
 */
class TorneoPistaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'torneo_id' => Torneo::factory(),
            'pista_id' => Pista::factory(),
            'orden' => 0,
            'disponibilidad' => function (array $atributos): array {
                $torneo = Torneo::query()->findOrFail((int) $atributos['torneo_id']);
                $tramos = [];

                foreach ($torneo->fecha_inicio->toPeriod($torneo->fecha_fin) as $dia) {
                    $tramos[] = ['fecha' => $dia->toDateString(), 'desde' => '09:00', 'hasta' => '21:00'];
                }

                return $tramos;
            },
        ];
    }
}
