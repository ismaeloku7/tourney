<?php

namespace App\Actions\Competicion;

use App\Competicion\EstimadorDeDuracion;
use App\Enums\EstadoPartido;
use App\Models\Partido;

/**
 * Lo que duran de verdad los partidos del organizador: se mide cada uno
 * al acabar en directo (desde que se le llama a pista hasta su resultado,
 * sin el calentamiento) y con eso se estima cuánto durarán los
 * siguientes de su formato, mejor que con la duración puesta a mano.
 */
final class DuracionesAprendidas
{
    /** Los últimos partidos medidos que se tienen en cuenta. */
    public const MAXIMO = 1000;

    /** Mediciones fuera de esto (minutos de juego) se descartan: un resultado metido tarde, un error… */
    public const MINIMO_CREIBLE = 15;

    public const MAXIMO_CREIBLE = 240;

    public function estimador(): EstimadorDeDuracion
    {
        $muestras = array_values(Partido::query()
            ->whereNotNull('minutos_jugados')
            ->whereNotNull('formato_medido_id')
            ->orderByDesc('registrado_en')
            ->limit(self::MAXIMO)
            ->get(['id', 'formato_medido_id', 'parejas_igualadas', 'minutos_jugados'])
            ->map(fn (Partido $p): array => ['formato' => (int) $p->formato_medido_id, 'igualados' => $p->parejas_igualadas, 'minutos' => (int) $p->minutos_jugados])
            ->all());

        return new EstimadorDeDuracion($muestras);
    }

    /** Los minutos de juego (sin calentamiento) que se esperan para este partido. */
    public function minutosDe(Partido $partido, ?EstimadorDeDuracion $estimador = null): int
    {
        $formato = $partido->formatoEfectivo();

        return ($estimador ?? $this->estimador())->minutos($formato->id, self::igualados($partido), $formato->duracion_estimada);
    }

    public static function igualados(Partido $partido): ?bool
    {
        return EstimadorDeDuracion::igualados($partido->local?->nivelMedio(), $partido->visitante?->nivelMedio());
    }

    /**
     * Al acabar un partido en directo: cuánto ha durado desde que entró en
     * pista (su hora de inicio, que en directo es cuando se le llamó), sin
     * el calentamiento. Los walkover y lo que no es creíble no cuentan.
     */
    public function medir(Partido $partido): void
    {
        $franja = $partido->franja;

        if ($franja === null || $partido->registrado_en === null || $partido->especial !== null
            || ! in_array($partido->estado, [EstadoPartido::Jugado, EstadoPartido::PendienteValidacion], true)) {
            return;
        }

        $calentamiento = $partido->fase->categoria->torneo->margen_entre_partidos_minutos;
        $minutos = intdiv($partido->registrado_en->getTimestamp() - $franja->inicio->getTimestamp(), 60) - $calentamiento;

        if ($minutos < self::MINIMO_CREIBLE || $minutos > self::MAXIMO_CREIBLE) {
            return;
        }

        $partido->forceFill([
            'minutos_jugados' => $minutos,
            'formato_medido_id' => $partido->formatoEfectivo()->id,
            'parejas_igualadas' => self::igualados($partido),
        ])->save();
    }
}
