<?php

namespace Database\Factories;

use App\Enums\EstadoFase;
use App\Enums\TipoDeFase;
use App\Models\Categoria;
use App\Models\Fase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Por defecto, una fase de grupos de 4 con dos clasificados por grupo. La
 * configuración es el array del DTO correspondiente.
 *
 * @extends Factory<Fase>
 */
class FaseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'categoria_id' => Categoria::factory(),
            'tipo' => TipoDeFase::Grupos,
            'nombre' => 'Fase de grupos',
            'orden' => 1,
            'configuracion' => self::configuracionDeGrupos(),
            'estado' => EstadoFase::Pendiente,
        ];
    }

    /**
     * @param  array<string, mixed>  $cambios
     * @return array<string, mixed>
     */
    public static function configuracionDeGrupos(array $cambios = []): array
    {
        return array_merge([
            'tamano_de_grupo' => 4,
            'modo_de_sorteo' => 'aleatorio',
            'semilla' => null,
            'partidos_por_participante' => null,
            'ida_y_vuelta' => false,
            'puntuacion' => ['victoria' => 2, 'derrota' => 0, 'walkover_favor' => 2, 'walkover_contra' => 0, 'no_jugado' => 0, 'por_set_ganado' => null],
            'criterios_de_desempate' => ['puntos', 'enfrentamiento_directo', 'diferencia_de_sets', 'sets_ganados', 'diferencia_de_juegos', 'juegos_ganados', 'sorteo'],
            'clasificados' => ['por_grupo' => 2, 'mejores_de_posicion' => null, 'cuantos_mejores' => 0],
        ], $cambios);
    }

    /**
     * @param  array<string, mixed>  $cambios
     * @return array<string, mixed>
     */
    public static function configuracionDeEliminatoria(array $cambios = []): array
    {
        return array_merge([
            'tamano_de_cuadro' => null,
            'regla_de_cruces' => 'entre_grupos',
            'evitar_reencuentros' => true,
            'tercer_y_cuarto_puesto' => false,
            'consolacion' => 'ninguna',
            'formatos_por_ronda' => [],
            'semilla' => null,
        ], $cambios);
    }

    /**
     * @param  array<string, mixed>  $cambios
     */
    public function grupos(array $cambios = []): static
    {
        return $this->state(['tipo' => TipoDeFase::Grupos, 'nombre' => 'Fase de grupos', 'orden' => 1, 'configuracion' => self::configuracionDeGrupos($cambios)]);
    }

    /**
     * @param  array<string, mixed>  $cambios
     */
    public function eliminatoria(array $cambios = []): static
    {
        return $this->state(['tipo' => TipoDeFase::Eliminatoria, 'nombre' => 'Cuadro final', 'orden' => 2, 'configuracion' => self::configuracionDeEliminatoria($cambios)]);
    }

    /**
     * @param  array<string, mixed>  $cambios
     */
    public function consolacion(array $cambios = []): static
    {
        return $this->state(['tipo' => TipoDeFase::Consolacion, 'nombre' => 'Consolación', 'orden' => 3, 'configuracion' => self::configuracionDeEliminatoria($cambios)]);
    }

    public function preparada(): static
    {
        return $this->state(['estado' => EstadoFase::Preparada]);
    }
}
