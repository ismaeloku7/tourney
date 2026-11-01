<?php

namespace Database\Factories;

use App\Enums\EstadoInscripcion;
use App\Enums\OrigenInscripcion;
use App\Models\Inscripcion;
use App\Models\Participante;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Por defecto, una inscripción confirmada del participante que se le pase
 * (o de uno nuevo), en su misma categoría.
 *
 * @extends Factory<Inscripcion>
 */
class InscripcionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'participante_id' => Participante::factory(),
            'categoria_id' => fn (array $atributos): int => (int) Participante::query()->findOrFail((int) $atributos['participante_id'])->categoria_id,
            'estado' => EstadoInscripcion::Confirmada,
            'origen' => OrigenInscripcion::Manual,
            'confirmada_en' => now(),
        ];
    }

    public function pendiente(): static
    {
        return $this->state(['estado' => EstadoInscripcion::Pendiente, 'confirmada_en' => null]);
    }

    public function enEspera(int $posicion = 1): static
    {
        return $this->state(['estado' => EstadoInscripcion::ListaDeEspera, 'posicion_espera' => $posicion, 'confirmada_en' => null]);
    }

    public function deBaja(): static
    {
        return $this->state(['estado' => EstadoInscripcion::Baja, 'baja_en' => now()]);
    }
}
