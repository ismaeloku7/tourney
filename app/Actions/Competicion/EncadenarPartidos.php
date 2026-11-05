<?php

namespace App\Actions\Competicion;

use App\Models\Franja;
use App\Models\Partido;
use App\Models\RegistroDeCambio;
use App\Models\Torneo;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Convierte la planificación por franjas en colas por pista: cada partido
 * con hora y pista recibe su orden en la pista (por la hora de su franja)
 * y una primera hora estimada. Lo que ya se ha jugado conserva su orden.
 * Devuelve cuántos partidos han quedado en cola.
 */
final class EncadenarPartidos
{
    public function __construct(private readonly ReestimarHorarios $reestimar = new ReestimarHorarios) {}

    public function handle(Torneo $torneo, User $usuario): int
    {
        if (! $torneo->encadenado()) {
            return 0;
        }

        $partidos = Partido::query()
            ->whereHas('fase.categoria', fn (Builder $q) => $q->where('torneo_id', $torneo->id))
            ->whereNotNull('pista_id')->whereNotNull('franja_id')
            ->with('franja')
            ->orderBy(Franja::query()->select('inicio')->whereColumn('franjas.id', 'partidos.franja_id'))
            ->orderBy('id')
            ->get();

        $enCola = 0;

        DB::transaction(function () use ($partidos, &$enCola): void {
            foreach ($partidos->groupBy(fn (Partido $p): string => $p->pista_id.'|'.$p->franja->inicio->toDateString()) as $cola) {
                $orden = 1;
                foreach ($cola as $partido) {
                    $partido->forceFill(['orden_en_pista' => $orden++, 'inicio_estimado' => $partido->inicio_estimado ?? $partido->franja->inicio])->saveQuietly();
                    $enCola++;
                }
            }
        });

        $this->reestimar->handle($torneo);

        RegistroDeCambio::anotar('encadenar_partidos', $torneo, $usuario, null, ['partidos' => $enCola]);

        return $enCola;
    }
}
