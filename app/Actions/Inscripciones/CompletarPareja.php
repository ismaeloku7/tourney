<?php

namespace App\Actions\Inscripciones;

use App\Competicion\Errores\OperacionNoPermitida;
use App\Enums\EstadoInscripcion;
use App\Models\Inscripcion;
use App\Models\Jugador;
use App\Models\Participante;
use App\Models\RegistroDeCambio;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Pone el compañero que le faltaba a una pareja (las importadas «sin
 * compañero»). Si se pide, la inscripción pasa a confirmada o, sin plaza,
 * a la lista de espera.
 */
final class CompletarPareja
{
    public function __construct(private readonly CambiarEstadoDeInscripcion $cambiar) {}

    /** @throws OperacionNoPermitida */
    public function handle(Inscripcion $inscripcion, Jugador $companero, bool $confirmar, ?User $usuario = null): Inscripcion
    {
        $participante = $inscripcion->participante;

        if ($participante->participanteJugadores()->count() >= 2) {
            throw new OperacionNoPermitida('Esta pareja ya tiene sus dos jugadores.');
        }

        $yaInscrito = Participante::query()->where('categoria_id', $inscripcion->categoria_id)
            ->whereHas('inscripcion', fn ($q) => $q->where('estado', '!=', EstadoInscripcion::Baja->value))
            ->whereHas('participanteJugadores', fn ($q) => $q->where('jugador_id', $companero->id))
            ->exists();

        if ($yaInscrito) {
            throw new OperacionNoPermitida("{$companero->nombre_completo} ya está inscrito en esta categoría.");
        }

        DB::transaction(function () use ($participante, $companero, $inscripcion, $usuario): void {
            $participante->participanteJugadores()->create(['jugador_id' => $companero->id, 'orden' => 2, 'confirmado_en' => now()]);
            RegistroDeCambio::anotar('completar_pareja', $inscripcion, $usuario, null, ['jugador' => $companero->id]);
        });

        if ($confirmar && $inscripcion->estado === EstadoInscripcion::Pendiente) {
            $destino = InscribirPareja::alCupo($inscripcion->categoria) ? EstadoInscripcion::ListaDeEspera : EstadoInscripcion::Confirmada;
            $this->cambiar->handle($inscripcion, $destino, $usuario);
        }

        return $inscripcion->refresh();
    }
}
