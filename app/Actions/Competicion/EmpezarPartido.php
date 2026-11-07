<?php

namespace App\Actions\Competicion;

use App\Competicion\Errores\OperacionNoPermitida;
use App\Enums\EstadoPartido;
use App\Enums\ResultadosPorJugadores;
use App\Models\Jugador;
use App\Models\Partido;
use App\Models\RegistroDeCambio;
use App\Models\User;

/**
 * El partido entra en pista: pasa a en juego con la hora real y el
 * marcador a cero. Lo hace la mesa o, si el torneo deja los resultados
 * a los jugadores, un jugador del partido.
 */
final class EmpezarPartido
{
    public function handle(Partido $partido, User|Jugador $quien): Partido
    {
        if (! in_array($partido->estado, [EstadoPartido::Pendiente, EstadoPartido::Programado], true)) {
            throw new OperacionNoPermitida('El partido ya ha empezado o ya tiene resultado.');
        }

        if ($partido->local_id === null || $partido->visitante_id === null) {
            throw new OperacionNoPermitida('El partido aún no tiene a los dos participantes.');
        }

        self::comprobarQuien($partido, $quien);

        $partido->fill(['estado' => EstadoPartido::EnJuego, 'empezado_en' => now(), 'marcador_en_vivo' => ['sets' => [], 'juegos' => [0, 0]]])->save();
        RegistroDeCambio::anotar('empezar_partido', $partido, $quien, null, ['empezado_en' => $partido->empezado_en?->toIso8601String()]);

        // Uno detrás de otro: el anterior de la pista acaba ahora y lo de detrás se reajusta.
        app(HorasEnDirecto::class)->trasEmpezar($partido);

        return $partido->refresh();
    }

    /** La mesa siempre; un jugador, solo en su partido y si el torneo deja a los jugadores. */
    public static function comprobarQuien(Partido $partido, User|Jugador $quien): void
    {
        if ($quien instanceof User) {
            return;
        }

        if ($partido->fase->categoria->torneo->resultados_por_jugadores === ResultadosPorJugadores::Desactivado) {
            throw new OperacionNoPermitida('En este torneo el marcador lo lleva la organización.');
        }

        $jugadores = [...($partido->local?->idsDeJugadores() ?? []), ...($partido->visitante?->idsDeJugadores() ?? [])];
        if (! in_array($quien->id, $jugadores, true)) {
            throw new OperacionNoPermitida('Solo los jugadores del partido pueden llevar su marcador.');
        }
    }
}
