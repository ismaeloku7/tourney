<?php

namespace App\Actions\Competicion;

use App\Competicion\Datos\DisponibilidadDePista;
use App\Models\Categoria;
use App\Models\FormatoPartido;
use App\Models\Torneo;
use Illuminate\Support\Carbon;

/**
 * Crea las franjas de cada pista del torneo a partir de su disponibilidad,
 * con la duración del formato más largo de sus categorías más el margen.
 * No toca franjas que ya existen (ocupadas o no). Devuelve cuántas crea.
 */
final class GenerarFranjas
{
    public function handle(Torneo $torneo): int
    {
        $duracion = $this->duracion($torneo) + $torneo->margen_entre_partidos_minutos;
        $creadas = 0;

        foreach ($torneo->torneoPistas as $torneoPista) {
            $disponibilidad = DisponibilidadDePista::desdeArray($torneoPista->disponibilidad);

            foreach ($disponibilidad->tramos as $tramo) {
                $inicio = Carbon::parse($tramo->fecha.' '.$tramo->desde);
                $fin = Carbon::parse($tramo->fecha.' '.$tramo->hasta);

                while ($inicio->copy()->addMinutes($duracion)->lte($fin)) {
                    $franja = $torneo->franjas()->firstOrCreate(
                        ['pista_id' => $torneoPista->pista_id, 'inicio' => $inicio],
                        ['fin' => $inicio->copy()->addMinutes($duracion), 'bloqueada' => false],
                    );

                    if ($franja->wasRecentlyCreated) {
                        $creadas++;
                    }

                    $inicio = $inicio->copy()->addMinutes($duracion);
                }
            }
        }

        return $creadas;
    }

    private function duracion(Torneo $torneo): int
    {
        $duraciones = $torneo->categorias->map(fn (Categoria $c): int => $c->formatoEfectivo()->duracion_estimada);

        if ($duraciones->isNotEmpty()) {
            return (int) $duraciones->max();
        }

        return FormatoPartido::query()->where('es_predeterminado', true)->value('duracion_estimada') ?? 60;
    }
}
