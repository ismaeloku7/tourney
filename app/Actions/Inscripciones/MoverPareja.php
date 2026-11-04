<?php

namespace App\Actions\Inscripciones;

use App\Competicion\Errores\OperacionNoPermitida;
use App\Enums\EstadoInscripcion;
use App\Models\Categoria;
use App\Models\Inscripcion;
use App\Models\Participante;
use App\Models\Partido;
use App\Models\RegistroDeCambio;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Pasa una pareja (su inscripción y sus jugadores) a otra categoría, del
 * mismo torneo o de otro. No se mueve una pareja que ya está en el sorteo
 * o en los partidos de su categoría, ni a una categoría donde ya juega
 * alguno de sus jugadores. Una pareja confirmada que no cabe en el cupo de
 * la nueva pasa a su lista de espera.
 */
final class MoverPareja
{
    /** @throws OperacionNoPermitida */
    public function handle(Inscripcion $inscripcion, Categoria $destino, ?User $usuario = null): Inscripcion
    {
        $participante = $inscripcion->participante;
        $origen = $inscripcion->categoria;

        if ($origen->is($destino)) {
            throw new OperacionNoPermitida("{$participante->nombre} ya está en {$destino->nombre}.");
        }

        $enJuego = $participante->grupos()->exists()
            || Partido::query()->where('local_id', $participante->id)->orWhere('visitante_id', $participante->id)->exists();
        if ($enJuego) {
            throw new OperacionNoPermitida("{$participante->nombre} ya está en el sorteo o en los partidos de {$origen->nombre}: no se puede mover.");
        }

        $repetido = Participante::query()->where('categoria_id', $destino->id)
            ->whereHas('inscripcion', fn ($q) => $q->where('estado', '!=', EstadoInscripcion::Baja->value))
            ->whereHas('participanteJugadores', fn ($q) => $q->whereIn('jugador_id', $participante->idsDeJugadores()))
            ->exists();
        if ($repetido) {
            throw new OperacionNoPermitida("Alguno de los jugadores de {$participante->nombre} ya está inscrito en {$destino->nombre}.");
        }

        return DB::transaction(function () use ($inscripcion, $participante, $origen, $destino, $usuario): Inscripcion {
            $estado = $inscripcion->estado;
            if ($estado === EstadoInscripcion::Confirmada && InscribirPareja::alCupo($destino)) {
                $estado = EstadoInscripcion::ListaDeEspera;
            }

            $participante->update(['categoria_id' => $destino->id, 'cabeza_de_serie' => null]);
            $inscripcion->update([
                'categoria_id' => $destino->id,
                'estado' => $estado,
                'posicion_espera' => $estado === EstadoInscripcion::ListaDeEspera ? InscribirPareja::siguientePosicionDeEspera($destino) : null,
            ]);

            RegistroDeCambio::anotar('mover_pareja', $inscripcion, $usuario,
                ['categoria' => $origen->nombre, 'torneo' => $origen->torneo->nombre],
                ['categoria' => $destino->nombre, 'torneo' => $destino->torneo->nombre, 'estado' => $estado->value]);

            return $inscripcion->refresh();
        });
    }
}
