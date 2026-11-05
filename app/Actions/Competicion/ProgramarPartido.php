<?php

namespace App\Actions\Competicion;

use App\Competicion\Errores\OperacionNoPermitida;
use App\Enums\EstadoPartido;
use App\Models\Franja;
use App\Models\Partido;
use App\Models\RegistroDeCambio;
use App\Models\User;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * Pone un partido a mano en una pista a una hora (o lo deja sin hora).
 * Reserva la pista para el calentamiento y la duración estimada, y no
 * deja pisar otro partido ni un bloqueo de esa pista. Lo que el
 * planificador hace en bloque, aquí de uno en uno, para los retoques.
 */
final class ProgramarPartido
{
    public function handle(Partido $partido, ?int $pista, ?DateTimeInterface $inicio, User $usuario): Partido
    {
        if (! $partido->estado->abierto() || $partido->estado === EstadoPartido::PendienteValidacion) {
            throw new OperacionNoPermitida('Un partido con resultado no se mueve de hora.');
        }

        $torneo = $partido->fase->categoria->torneo;
        $antes = $partido->only(['franja_id', 'pista_id', 'estado']);

        DB::transaction(function () use ($partido, $pista, $inicio, $torneo): void {
            $franjaAnterior = $partido->franja_id;

            if ($pista === null || $inicio === null) {
                $partido->fill(['franja_id' => null, 'pista_id' => null, 'estado' => EstadoPartido::Pendiente])->save();
                if ($franjaAnterior !== null) {
                    Franja::query()->whereKey($franjaAnterior)->where('bloqueada', false)->delete();
                }

                return;
            }

            if (! $torneo->torneoPistas()->where('pista_id', $pista)->exists()) {
                throw new OperacionNoPermitida('Esa pista no es de este torneo.');
            }

            $desde = CarbonImmutable::instance($inicio)->second(0);
            $hasta = $desde->addMinutes($torneo->margen_entre_partidos_minutos + app(DuracionesAprendidas::class)->minutosDe($partido));

            $choca = Franja::query()->with('partido.local', 'partido.visitante')
                ->where('torneo_id', $torneo->id)
                ->where('pista_id', $pista)
                ->when($franjaAnterior !== null, fn ($q) => $q->whereKeyNot($franjaAnterior))
                ->where('inicio', '<', $hasta)
                ->where('fin', '>', $desde)
                ->where(fn ($q) => $q->where('bloqueada', true)->orWhereHas('partido'))
                ->orderBy('inicio')
                ->first();

            if ($choca !== null) {
                $que = $choca->partido !== null
                    ? 'el partido '.($choca->partido->local->nombre ?? '?').' – '.($choca->partido->visitante->nombre ?? '?')
                    : 'un bloqueo';
                throw new OperacionNoPermitida('La pista está ocupada de '.$choca->inicio->format('H:i').' a '.$choca->fin->format('H:i').' por '.$que.'.');
            }

            $datos = ['torneo_id' => $torneo->id, 'pista_id' => $pista, 'inicio' => $desde, 'fin' => $hasta, 'inicio_previsto' => $desde, 'bloqueada' => false];
            $franja = $franjaAnterior !== null ? Franja::query()->find($franjaAnterior) : null;
            if ($franja !== null) {
                $franja->update($datos);
            } else {
                $franja = Franja::query()->create($datos);
            }

            $partido->fill(['franja_id' => $franja->id, 'pista_id' => $pista, 'estado' => EstadoPartido::Programado])->save();
        });

        RegistroDeCambio::anotar('programar_partido', $partido, $usuario, $antes, $partido->only(['franja_id', 'pista_id', 'estado']));

        return $partido;
    }
}
