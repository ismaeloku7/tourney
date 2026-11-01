<?php

namespace App\Actions\Jugadores;

use App\Actions\Inscripciones\CambiarEstadoDeInscripcion;
use App\Competicion\Errores\OperacionNoPermitida;
use App\Enums\EstadoInscripcion;
use App\Enums\EstadoTorneo;
use App\Models\Inscripcion;
use App\Models\Jugador;
use App\Models\RegistroDeCambio;

/**
 * Un jugador saca a su pareja de una categoría. Solo mientras la
 * inscripción del torneo está abierta; después, lo hace la organización.
 */
final class DarseDeBaja
{
    public function __construct(private readonly CambiarEstadoDeInscripcion $cambiar) {}

    /** @throws OperacionNoPermitida */
    public function handle(Jugador $jugador, Inscripcion $inscripcion): Inscripcion
    {
        if (! in_array($jugador->id, $inscripcion->participante->idsDeJugadores(), true)) {
            throw new OperacionNoPermitida('Esa inscripción no es tuya.');
        }

        if ($inscripcion->categoria->torneo->estado !== EstadoTorneo::InscripcionAbierta) {
            throw new OperacionNoPermitida('La inscripción ya está cerrada: para darte de baja escribe a la organización.');
        }

        if ($inscripcion->estado === EstadoInscripcion::Baja) {
            return $inscripcion;
        }

        $inscripcion = $this->cambiar->handle($inscripcion, EstadoInscripcion::Baja);
        RegistroDeCambio::anotar('baja_del_jugador', $inscripcion, $jugador, null, ['estado' => EstadoInscripcion::Baja->value]);

        return $inscripcion;
    }
}
