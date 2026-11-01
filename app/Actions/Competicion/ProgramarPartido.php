<?php

namespace App\Actions\Competicion;

use App\Competicion\Errores\OperacionNoPermitida;
use App\Enums\EstadoPartido;
use App\Models\Franja;
use App\Models\Partido;
use App\Models\RegistroDeCambio;
use App\Models\User;

/**
 * Pone un partido en una franja libre a mano (o lo deja sin hora). Lo que
 * el planificador hace en bloque, aquí de uno en uno, para los retoques.
 */
final class ProgramarPartido
{
    public function handle(Partido $partido, ?Franja $franja, User $usuario): Partido
    {
        if (! $partido->estado->abierto() || $partido->estado === EstadoPartido::PendienteValidacion) {
            throw new OperacionNoPermitida('Un partido con resultado no se mueve de hora.');
        }

        if ($franja !== null) {
            if ($franja->bloqueada) {
                throw new OperacionNoPermitida('Esa franja está bloqueada.');
            }

            if ($franja->partido()->whereKeyNot($partido->id)->exists()) {
                throw new OperacionNoPermitida('Esa franja ya tiene un partido.');
            }
        }

        $antes = $partido->only(['franja_id', 'pista_id', 'estado']);

        $partido->fill([
            'franja_id' => $franja?->id,
            'pista_id' => $franja?->pista_id,
            'estado' => $franja !== null ? EstadoPartido::Programado : EstadoPartido::Pendiente,
        ])->save();

        RegistroDeCambio::anotar('programar_partido', $partido, $usuario, $antes, $partido->only(['franja_id', 'pista_id', 'estado']));

        return $partido;
    }
}
