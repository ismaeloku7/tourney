<?php

namespace App\Competicion\Datos;

use App\Competicion\Errores\ConfiguracionInvalida;
use DateTimeImmutable;

/**
 * Los tramos en que una pista está disponible en un torneo, validados: lo
 * que se guarda en `torneo_pistas.disponibilidad`.
 */
final readonly class DisponibilidadDePista
{
    /** @param  list<TramoDeDisponibilidad>  $tramos */
    public function __construct(public array $tramos) {}

    /**
     * @param  list<array<string, mixed>>  $datos
     *
     * @throws ConfiguracionInvalida
     */
    public static function desdeArray(array $datos): self
    {
        $errores = [];
        $tramos = [];

        foreach (array_values($datos) as $i => $tramo) {
            $n = $i + 1;
            $fecha = (string) ($tramo['fecha'] ?? '');
            $desde = (string) ($tramo['desde'] ?? '');
            $hasta = (string) ($tramo['hasta'] ?? '');

            $dia = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
            if ($dia === false || $dia->format('Y-m-d') !== $fecha) {
                $errores[] = "El tramo {$n} no tiene una fecha válida (AAAA-MM-DD).";
            }
            foreach (['desde' => $desde, 'hasta' => $hasta] as $campo => $hora) {
                if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hora) !== 1) {
                    $errores[] = "El tramo {$n} no tiene una hora válida en «{$campo}» (HH:MM).";
                }
            }
            if ($desde !== '' && $hasta !== '' && $desde >= $hasta) {
                $errores[] = "En el tramo {$n}, la hora de fin tiene que ir después de la de inicio.";
            }

            $tramos[] = new TramoDeDisponibilidad($fecha, $desde, $hasta);
        }

        if ($tramos === []) {
            $errores[] = 'Hace falta al menos un tramo de disponibilidad.';
        }

        if ($errores !== []) {
            throw new ConfiguracionInvalida($errores);
        }

        return new self($tramos);
    }

    /** @return list<array{fecha: string, desde: string, hasta: string}> */
    public function toArray(): array
    {
        return array_map(fn (TramoDeDisponibilidad $tramo): array => $tramo->toArray(), $this->tramos);
    }
}
