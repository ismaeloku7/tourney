<?php

namespace Database\Factories;

use App\Enums\EstadoTorneo;
use App\Enums\ResultadosPorJugadores;
use App\Models\Torneo;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Torneo>
 */
class TorneoFactory extends Factory
{
    public function definition(): array
    {
        $nombre = 'Open '.fake()->unique()->city().' '.fake()->year();
        $inicio = now()->addDays(fake()->numberBetween(7, 60))->startOfDay();

        return [
            'nombre' => $nombre,
            'slug' => Str::slug($nombre).'-'.Str::lower(Str::random(4)),
            'deporte' => 'padel',
            'fecha_inicio' => $inicio->toDateString(),
            'fecha_fin' => $inicio->addDays(1)->toDateString(),
            'inscripcion_desde' => now()->subDay(),
            'inscripcion_hasta' => $inicio->subDays(2),
            'inscripcion_online' => true,
            'estado' => EstadoTorneo::Borrador,
            'listado_en_plataforma' => false,
            'resultados_por_jugadores' => ResultadosPorJugadores::Desactivado,
            'minutos_para_validar' => 30,
            'privacidad_alias' => false,
            'descanso_minimo_minutos' => 30,
            'margen_entre_partidos_minutos' => 0,
        ];
    }

    public function enEstado(EstadoTorneo $estado): static
    {
        return $this->state(['estado' => $estado, 'publicado_en' => $estado->publicado() ? now() : null]);
    }

    /** La inscripción la hace solo la organización, a mano. */
    public function soloInscribeLaOrganizacion(): static
    {
        return $this->state(['inscripcion_online' => false]);
    }

    public function conInscripcionAbierta(): static
    {
        return $this->enEstado(EstadoTorneo::InscripcionAbierta);
    }

    public function enSorteo(): static
    {
        return $this->enEstado(EstadoTorneo::Sorteo);
    }

    public function enJuego(): static
    {
        return $this->enEstado(EstadoTorneo::EnJuego)->state(['horarios_publicados_en' => now()]);
    }

    public function finalizado(): static
    {
        return $this->enEstado(EstadoTorneo::Finalizado)->state(['horarios_publicados_en' => now(), 'finalizado_en' => now()]);
    }
}
