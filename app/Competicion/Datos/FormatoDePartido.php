<?php

namespace App\Competicion\Datos;

use App\Competicion\Errores\ConfiguracionInvalida;

/**
 * Las reglas de un partido: con ellas el motor valida resultados y el
 * planificador sabe cuánto dura. Los valores por defecto son los del
 * briefing: al mejor de 3 sets a 6 con tie-break a 7.
 */
final readonly class FormatoDePartido
{
    public function __construct(
        public int $sets = 3,
        public int $juegosPorSet = 6,
        public int $diferenciaParaGanarSet = 2,
        public ?int $tieBreakEn = 6,
        public int $tieBreakPuntos = 7,
        public bool $superTieBreakFinal = false,
        public int $superTieBreakPuntos = 10,
        public ?int $porTiempoMinutos = null,
        public int $duracionEstimada = 60,
    ) {}

    public function setsParaGanar(): int
    {
        return intdiv($this->sets, 2) + 1;
    }

    /** El set en el que, si está activo, se juega el super tie-break. */
    public function setDecisivo(): int
    {
        return $this->sets;
    }

    /** A cuántos juegos iguales toca el tie-break en un set de este formato. */
    public static function tieBreakParaJuegos(int $juegosPorSet): int
    {
        return $juegosPorSet === 9 ? 8 : $juegosPorSet;
    }

    /**
     * @param  array<string, mixed>  $datos
     *
     * @throws ConfiguracionInvalida
     */
    public static function desdeArray(array $datos): self
    {
        $errores = [];
        $sets = (int) ($datos['sets'] ?? 3);
        $juegos = (int) ($datos['juegos_por_set'] ?? 6);
        $diferencia = (int) ($datos['diferencia_para_ganar_set'] ?? 2);
        $tieBreakEn = isset($datos['tie_break_en']) ? (int) $datos['tie_break_en'] : null;
        $tieBreakPuntos = (int) ($datos['tie_break_puntos'] ?? 7);
        $superTieBreak = (bool) ($datos['super_tie_break_final'] ?? false);
        $superTieBreakPuntos = (int) ($datos['super_tie_break_puntos'] ?? 10);
        $porTiempo = isset($datos['por_tiempo_minutos']) ? (int) $datos['por_tiempo_minutos'] : null;
        $duracion = (int) ($datos['duracion_estimada'] ?? 60);

        if (! in_array($sets, [1, 3, 5], true)) {
            $errores[] = 'El número de sets tiene que ser 1, 3 o 5.';
        }
        if (! in_array($juegos, [4, 6, 9], true)) {
            $errores[] = 'Los juegos por set tienen que ser 4, 6 o 9.';
        }
        if (! in_array($diferencia, [1, 2], true)) {
            $errores[] = 'La diferencia para ganar el set tiene que ser 1 o 2.';
        }
        if ($tieBreakEn !== null && $tieBreakEn !== self::tieBreakParaJuegos($juegos)) {
            $errores[] = 'El tie-break de un set a '.$juegos.' se juega a '.self::tieBreakParaJuegos($juegos).' iguales.';
        }
        if ($tieBreakPuntos < 5) {
            $errores[] = 'El tie-break se juega al menos a 5 puntos.';
        }
        if ($superTieBreak && $sets === 1) {
            $errores[] = 'El super tie-break sustituye al set decisivo: hace falta más de un set.';
        }
        if ($superTieBreakPuntos < 5) {
            $errores[] = 'El super tie-break se juega al menos a 5 puntos.';
        }
        if ($porTiempo !== null && $porTiempo < 5) {
            $errores[] = 'Un partido por tiempo dura al menos 5 minutos.';
        }
        if ($duracion < 5) {
            $errores[] = 'La duración estimada es de al menos 5 minutos.';
        }

        if ($errores !== []) {
            throw new ConfiguracionInvalida($errores);
        }

        return new self($sets, $juegos, $diferencia, $tieBreakEn, $tieBreakPuntos, $superTieBreak, $superTieBreakPuntos, $porTiempo, $duracion);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'sets' => $this->sets,
            'juegos_por_set' => $this->juegosPorSet,
            'diferencia_para_ganar_set' => $this->diferenciaParaGanarSet,
            'tie_break_en' => $this->tieBreakEn,
            'tie_break_puntos' => $this->tieBreakPuntos,
            'super_tie_break_final' => $this->superTieBreakFinal,
            'super_tie_break_puntos' => $this->superTieBreakPuntos,
            'por_tiempo_minutos' => $this->porTiempoMinutos,
            'duracion_estimada' => $this->duracionEstimada,
        ];
    }
}
