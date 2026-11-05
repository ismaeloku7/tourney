<?php

namespace App\Competicion;

/**
 * Cuánto dura un partido según lo que han durado los ya jugados: la
 * mediana de su formato y, si hay bastantes, la de su formato entre
 * parejas igualadas (o no) de nivel. Sin bastantes partidos medidos, la
 * duración estimada a mano.
 */
final readonly class EstimadorDeDuracion
{
    /** Partidos medidos que hacen falta para fiarse de una mediana. */
    public const MINIMO_DE_PARTIDOS = 5;

    /** Diferencia de nivel medio hasta la que dos parejas van igualadas. */
    public const IGUALADOS_HASTA = 0.5;

    /** @param  list<array{formato: int, igualados: bool|null, minutos: int}>  $muestras */
    public function __construct(private array $muestras = []) {}

    /** Si dos parejas van igualadas de nivel; null si a alguna le falta. */
    public static function igualados(?float $nivelLocal, ?float $nivelVisitante): ?bool
    {
        if ($nivelLocal === null || $nivelVisitante === null) {
            return null;
        }

        return abs($nivelLocal - $nivelVisitante) <= self::IGUALADOS_HASTA;
    }

    public function minutos(int $formato, ?bool $igualados, int $porDefecto): int
    {
        if ($igualados !== null) {
            $mediana = $this->mediana(fn (array $m): bool => $m['formato'] === $formato && $m['igualados'] === $igualados);
            if ($mediana !== null) {
                return $mediana;
            }
        }

        return $this->mediana(fn (array $m): bool => $m['formato'] === $formato) ?? $porDefecto;
    }

    /**
     * La mediana de un formato y de cuántos partidos sale, o null si aún no
     * hay bastantes.
     *
     * @return array{minutos: int, partidos: int}|null
     */
    public function resumen(int $formato): ?array
    {
        $partidos = count(array_filter($this->muestras, fn (array $m): bool => $m['formato'] === $formato));
        $mediana = $this->mediana(fn (array $m): bool => $m['formato'] === $formato);

        return $mediana === null ? null : ['minutos' => $mediana, 'partidos' => $partidos];
    }

    public function partidosMedidos(): int
    {
        return count($this->muestras);
    }

    /** @param  callable(array{formato: int, igualados: bool|null, minutos: int}): bool  $filtro */
    private function mediana(callable $filtro): ?int
    {
        $minutos = array_values(array_map(fn (array $m): int => $m['minutos'], array_filter($this->muestras, $filtro)));

        if (count($minutos) < self::MINIMO_DE_PARTIDOS) {
            return null;
        }

        sort($minutos);
        $mitad = intdiv(count($minutos), 2);

        return count($minutos) % 2 === 1 ? $minutos[$mitad] : (int) round(($minutos[$mitad - 1] + $minutos[$mitad]) / 2);
    }
}
