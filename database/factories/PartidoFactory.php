<?php

namespace Database\Factories;

use App\Enums\EstadoPartido;
use App\Models\Fase;
use App\Models\Participante;
use App\Models\Partido;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Un partido pendiente entre dos participantes nuevos de la categoría de
 * la fase.
 *
 * @extends Factory<Partido>
 */
class PartidoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'fase_id' => Fase::factory(),
            'local_id' => fn (array $atributos): int => Participante::factory()->create(['categoria_id' => Fase::query()->findOrFail($atributos['fase_id'])->categoria_id])->id,
            'visitante_id' => fn (array $atributos): int => Participante::factory()->create(['categoria_id' => Fase::query()->findOrFail($atributos['fase_id'])->categoria_id])->id,
            'estado' => EstadoPartido::Pendiente,
            'es_tercer_puesto' => false,
        ];
    }

    /**
     * Jugado con este resultado en juegos por set, `[[6, 3], [6, 4]]`;
     * gana el local salvo que los sets digan otra cosa.
     *
     * @param  list<array{0: int, 1: int}>  $sets
     */
    public function jugado(array $sets = [[6, 3], [6, 4]]): static
    {
        return $this->state(['estado' => EstadoPartido::Jugado, 'registrado_en' => now()])
            ->afterCreating(function (Partido $partido) use ($sets): void {
                $setsLocal = 0;
                $setsVisitante = 0;

                foreach (array_values($sets) as $i => [$local, $visitante]) {
                    $partido->sets()->create(['numero' => $i + 1, 'juegos_local' => $local, 'juegos_visitante' => $visitante]);
                    $local > $visitante ? $setsLocal++ : $setsVisitante++;
                }

                $partido->ganador_id = $setsLocal >= $setsVisitante ? $partido->local_id : $partido->visitante_id;
                $partido->save();
            });
    }

    public function programado(): static
    {
        return $this->state(['estado' => EstadoPartido::Programado]);
    }
}
