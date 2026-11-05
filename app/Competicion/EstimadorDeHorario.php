<?php

namespace App\Competicion;

use App\Competicion\Datos\PartidoEnCola;
use DateInterval;
use DateTimeImmutable;

/**
 * A qué hora empieza cada partido de la cola de una pista, con lo que ya
 * se ha jugado: el primero, a la apertura más el calentamiento largo (se
 * calienta antes); los demás, cuando acaba el anterior (o ahora, si se
 * ha alargado) más el calentamiento: largo si para alguna de las dos
 * parejas es su primer partido, corto si las dos ya han jugado. Un
 * partido en juego mantiene su hora real; uno cerrado ya no cuenta más
 * que por cuándo acabó.
 */
final readonly class EstimadorDeHorario
{
    /**
     * @param  list<PartidoEnCola>  $cola  En orden de pista.
     * @param  list<int>  $yaHanJugado  Participantes que ya han jugado (o juegan) hoy en cualquier pista.
     * @return array<int, DateTimeImmutable> Id de partido → inicio estimado, solo de los que no han acabado.
     */
    public function estimar(array $cola, DateTimeImmutable $apertura, DateTimeImmutable $ahora, int $calentamientoPrimero, int $calentamientoResto, array $yaHanJugado = []): array
    {
        usort($cola, fn (PartidoEnCola $a, PartidoEnCola $b): int => $a->orden <=> $b->orden);

        $jugaron = array_fill_keys($yaHanJugado, true);
        $libre = null;
        $estimados = [];

        foreach ($cola as $partido) {
            if ($partido->cerrado) {
                $libre = $partido->terminadoEn ?? $libre;
                $this->anotar($jugaron, $partido);

                continue;
            }

            if ($partido->empezadoEn !== null) {
                $estimados[$partido->id] = $partido->empezadoEn;
                $libre = $partido->empezadoEn->add(new DateInterval("PT{$partido->duracionMinutos}M"));
                $this->anotar($jugaron, $partido);

                continue;
            }

            $primeroDeAlguno = ($partido->local !== null && ! isset($jugaron[$partido->local]))
                || ($partido->visitante !== null && ! isset($jugaron[$partido->visitante]));
            $calentamiento = $primeroDeAlguno ? $calentamientoPrimero : $calentamientoResto;

            $inicio = $libre === null
                ? $apertura->add(new DateInterval("PT{$calentamientoPrimero}M"))
                : ($libre > $ahora ? $libre : $ahora)->add(new DateInterval("PT{$calentamiento}M"));

            $estimados[$partido->id] = $inicio;
            $libre = $inicio->add(new DateInterval("PT{$partido->duracionMinutos}M"));
            $this->anotar($jugaron, $partido);
        }

        return $estimados;
    }

    /** @param  array<int, bool>  $jugaron */
    private function anotar(array &$jugaron, PartidoEnCola $partido): void
    {
        foreach ([$partido->local, $partido->visitante] as $participante) {
            if ($participante !== null) {
                $jugaron[$participante] = true;
            }
        }
    }
}
