<?php

namespace App\Actions\Competicion;

use App\Competicion\Datos\PartidoEnCola;
use App\Competicion\EstimadorDeHorario;
use App\Enums\EstadoPartido;
use App\Models\Partido;
use App\Models\Torneo;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Vuelve a calcular la hora estimada de los partidos encadenados de un
 * torneo, pista a pista y día a día, con lo que ya se ha jugado. Se llama
 * al encadenar, al empezar un partido y al cerrarlo. Devuelve cuántos
 * partidos han cambiado de hora.
 */
final class ReestimarHorarios
{
    public function __construct(private readonly EstimadorDeHorario $estimador = new EstimadorDeHorario) {}

    public function handle(Torneo $torneo, ?int $pistaId = null): int
    {
        if (! $torneo->encadenado()) {
            return 0;
        }

        $partidos = Partido::query()
            ->whereHas('fase.categoria', fn (Builder $q) => $q->where('torneo_id', $torneo->id))
            ->whereNotNull('orden_en_pista')->whereNotNull('pista_id')->whereNotNull('franja_id')
            ->when($pistaId !== null, fn (Builder $q) => $q->where('pista_id', $pistaId))
            ->with(['franja', 'fase.categoria'])
            ->get();

        $ahora = CarbonImmutable::now();
        $cambiados = 0;

        foreach ($partidos->groupBy(fn (Partido $p): string => $p->pista_id.'|'.$p->franja->inicio->toDateString()) as $clave => $cola) {
            $dia = explode('|', (string) $clave)[1];
            $apertura = $cola->min(fn (Partido $p): CarbonImmutable => $p->franja->inicio->toImmutable());

            $estimados = $this->estimador->estimar(
                array_values($cola->map(fn (Partido $p): PartidoEnCola => new PartidoEnCola(
                    id: $p->id,
                    orden: (int) $p->orden_en_pista,
                    local: $p->local_id,
                    visitante: $p->visitante_id,
                    duracionMinutos: $p->formatoEfectivo()->duracion_estimada,
                    empezadoEn: $p->empezado_en?->toImmutable(),
                    terminadoEn: $p->terminado_en?->toImmutable(),
                    cerrado: $p->estado->cerrado() || $p->estado === EstadoPartido::PendienteValidacion,
                ))->all()),
                $apertura,
                $ahora,
                $torneo->calentamiento_primero,
                $torneo->calentamiento_resto,
                $this->yaHanJugado($torneo, $dia, $cola),
            );

            foreach ($cola as $partido) {
                $nuevo = $estimados[$partido->id] ?? null;
                if ($nuevo === null || $partido->inicio_estimado?->equalTo($nuevo)) {
                    continue;
                }

                $partido->forceFill(['inicio_estimado' => $nuevo])->saveQuietly();
                $cambiados++;
            }
        }

        return $cambiados;
    }

    /**
     * Quién ya ha jugado (o está jugando) ese día en otra pista: su
     * siguiente partido lleva el calentamiento corto.
     *
     * @param  Collection<(int|string), Partido>  $cola
     * @return list<int>
     */
    private function yaHanJugado(Torneo $torneo, string $dia, Collection $cola): array
    {
        $enEstaCola = $cola->pluck('id')->all();

        $otros = Partido::query()
            ->whereHas('fase.categoria', fn (Builder $q) => $q->where('torneo_id', $torneo->id))
            ->whereKeyNot($enEstaCola)
            ->where(fn (Builder $q) => $q->whereDate('empezado_en', $dia)->orWhereDate('terminado_en', $dia))
            ->get(['local_id', 'visitante_id']);

        return array_values(array_unique(array_filter([...$otros->pluck('local_id')->all(), ...$otros->pluck('visitante_id')->all()], fn (mixed $id): bool => $id !== null)));
    }
}
