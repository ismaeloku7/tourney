<?php

namespace Database\Factories;

use App\Models\FormatoPartido;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Por defecto, el formato estándar: al mejor de 3 sets a 6 con tie-break.
 *
 * @extends Factory<FormatoPartido>
 */
class FormatoPartidoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nombre' => 'Al mejor de 3 sets a 6',
            'sets' => 3,
            'juegos_por_set' => 6,
            'diferencia_para_ganar_set' => 2,
            'tie_break_en' => 6,
            'tie_break_puntos' => 7,
            'super_tie_break_final' => false,
            'super_tie_break_puntos' => 10,
            'punto_de_oro' => true,
            'por_tiempo_minutos' => null,
            'duracion_estimada' => 75,
            'es_predeterminado' => false,
        ];
    }

    public function predeterminado(): static
    {
        return $this->state(['es_predeterminado' => true]);
    }

    public function unSetA(int $juegos, ?int $tieBreakEn = null): static
    {
        return $this->state(['nombre' => "Un set a {$juegos}", 'sets' => 1, 'juegos_por_set' => $juegos, 'tie_break_en' => $tieBreakEn, 'duracion_estimada' => 40]);
    }

    public function conSuperTieBreak(): static
    {
        return $this->state(['nombre' => 'Al mejor de 3 con super tie-break', 'super_tie_break_final' => true, 'duracion_estimada' => 60]);
    }

    public function porTiempo(int $minutos): static
    {
        return $this->state(['nombre' => "Por tiempo, {$minutos} minutos", 'sets' => 1, 'tie_break_en' => null, 'por_tiempo_minutos' => $minutos, 'duracion_estimada' => $minutos]);
    }
}
