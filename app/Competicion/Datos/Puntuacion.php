<?php

namespace App\Competicion\Datos;

use App\Competicion\Errores\ConfiguracionInvalida;

/**
 * Los puntos de la clasificación: por victoria, derrota, walkover a favor
 * y en contra y partido no jugado; opcionalmente por set ganado. Y cómo
 * cuenta un walkover en sets y juegos (2-0 y 6-0 por defecto).
 */
final readonly class Puntuacion
{
    public function __construct(
        public int $victoria = 2,
        public int $derrota = 0,
        public int $walkoverFavor = 2,
        public int $walkoverContra = 0,
        public int $noJugado = 0,
        public ?int $porSetGanado = null,
        public int $walkoverSets = 2,
        public int $walkoverJuegosPorSet = 6,
    ) {}

    /**
     * @param  array<string, mixed>  $datos
     *
     * @throws ConfiguracionInvalida
     */
    public static function desdeArray(array $datos): self
    {
        $errores = [];

        foreach (['victoria', 'derrota', 'walkover_favor', 'walkover_contra', 'no_jugado', 'walkover_sets', 'walkover_juegos_por_set'] as $campo) {
            if (isset($datos[$campo]) && (! is_numeric($datos[$campo]) || (int) $datos[$campo] < 0)) {
                $errores[] = "Los puntos de «{$campo}» tienen que ser un número positivo o cero.";
            }
        }

        if (isset($datos['por_set_ganado']) && (! is_numeric($datos['por_set_ganado']) || (int) $datos['por_set_ganado'] < 0)) {
            $errores[] = 'Los puntos por set ganado tienen que ser un número positivo o cero.';
        }

        if ($errores !== []) {
            throw new ConfiguracionInvalida($errores);
        }

        return new self(
            (int) ($datos['victoria'] ?? 2),
            (int) ($datos['derrota'] ?? 0),
            (int) ($datos['walkover_favor'] ?? 2),
            (int) ($datos['walkover_contra'] ?? 0),
            (int) ($datos['no_jugado'] ?? 0),
            isset($datos['por_set_ganado']) ? (int) $datos['por_set_ganado'] : null,
            (int) ($datos['walkover_sets'] ?? 2),
            (int) ($datos['walkover_juegos_por_set'] ?? 6),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'victoria' => $this->victoria,
            'derrota' => $this->derrota,
            'walkover_favor' => $this->walkoverFavor,
            'walkover_contra' => $this->walkoverContra,
            'no_jugado' => $this->noJugado,
            'por_set_ganado' => $this->porSetGanado,
            'walkover_sets' => $this->walkoverSets,
            'walkover_juegos_por_set' => $this->walkoverJuegosPorSet,
        ];
    }
}
