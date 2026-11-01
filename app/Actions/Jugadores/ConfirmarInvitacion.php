<?php

namespace App\Actions\Jugadores;

use App\Actions\Inscripciones\InscribirPareja;
use App\Avisos\Avisos;
use App\Enums\EstadoInscripcion;
use App\Models\Inscripcion;
use App\Models\ParticipanteJugador;
use App\Models\RegistroDeCambio;
use Illuminate\Support\Facades\DB;

/**
 * El compañero invitado acepta: queda confirmado y la inscripción pasa a
 * confirmada o, si ya no hay plaza, a la lista de espera.
 */
final class ConfirmarInvitacion
{
    /** La fila del invitado, o nula si el token no vale o ha caducado. */
    public static function buscar(string $token): ?ParticipanteJugador
    {
        return ParticipanteJugador::query()
            ->where('invitacion_token', hash('sha256', $token))
            ->whereNull('confirmado_en')
            ->where('invitacion_expira_en', '>', now())
            ->with(['jugador', 'participante.categoria.torneo', 'participante.inscripcion'])
            ->first();
    }

    public function handle(ParticipanteJugador $invitacion): Inscripcion
    {
        $estabaPendiente = $invitacion->participante->inscripcion?->estado === EstadoInscripcion::Pendiente;

        $resultado = DB::transaction(function () use ($invitacion): Inscripcion {
            $invitacion->forceFill(['confirmado_en' => now(), 'invitacion_token' => null, 'invitacion_expira_en' => null])->save();

            $jugador = $invitacion->jugador;
            if ($jugador->consentimiento_en === null) {
                $jugador->forceFill(['consentimiento_en' => now()])->save();
            }

            $inscripcion = $invitacion->participante->inscripcion;
            assert($inscripcion instanceof Inscripcion);

            if ($inscripcion->estado === EstadoInscripcion::Pendiente) {
                $categoria = $inscripcion->categoria;
                $alCupo = InscribirPareja::alCupo($categoria);

                $inscripcion->fill([
                    'estado' => $alCupo ? EstadoInscripcion::ListaDeEspera : EstadoInscripcion::Confirmada,
                    'posicion_espera' => $alCupo ? InscribirPareja::siguientePosicionDeEspera($categoria) : null,
                    'confirmada_en' => $alCupo ? null : now(),
                ])->save();

                RegistroDeCambio::anotar('confirmar_invitacion', $inscripcion, $jugador, ['estado' => EstadoInscripcion::Pendiente->value], ['estado' => $inscripcion->estado->value]);
            }

            return $inscripcion->refresh();
        });

        if ($estabaPendiente && $resultado->estado === EstadoInscripcion::Confirmada) {
            app(Avisos::class)->inscripcionConfirmada($resultado);
        }

        return $resultado;
    }
}
