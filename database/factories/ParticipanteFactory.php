<?php

namespace Database\Factories;

use App\Enums\EstadoParticipante;
use App\Models\Categoria;
use App\Models\Jugador;
use App\Models\Participante;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Una pareja con dos jugadores nuevos y su nombre «Apellido / Apellido».
 *
 * @extends Factory<Participante>
 */
class ParticipanteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'categoria_id' => Categoria::factory(),
            'nombre' => fake()->lastName().' / '.fake()->lastName(),
            'cabeza_de_serie' => null,
            'estado' => EstadoParticipante::Activo,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Participante $participante): void {
            $jugadores = $participante->jugadoresPendientes ?? Jugador::factory()->count(2)->create()->all();
            $participante->jugadoresPendientes = null;

            foreach (array_values($jugadores) as $i => $jugador) {
                $participante->participanteJugadores()->updateOrCreate(['jugador_id' => $jugador->id], ['orden' => $i + 1, 'confirmado_en' => now()]);
            }

            $participante->nombre = Participante::nombreDesdeJugadores(...$jugadores);
            $participante->save();
        });
    }

    public function conCabezaDeSerie(int $numero): static
    {
        return $this->state(['cabeza_de_serie' => $numero]);
    }

    public function retirado(): static
    {
        return $this->state(['estado' => EstadoParticipante::Retirado]);
    }

    /**
     * Con estos jugadores en vez de jugadores nuevos.
     */
    public function conJugadores(Jugador ...$jugadores): static
    {
        return $this->afterMaking(function (Participante $participante) use ($jugadores): void {
            $participante->jugadoresPendientes = array_values($jugadores);
        });
    }
}
