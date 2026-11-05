<?php

namespace App\Actions\Competicion;

use App\Avisos\Avisos;
use App\Competicion\Datos\Resultado;
use App\Competicion\Errores\OperacionNoPermitida;
use App\Competicion\Errores\ResultadoInvalido;
use App\Competicion\ValidadorDeResultado;
use App\Enums\EstadoPartido;
use App\Enums\ResultadosPorJugadores;
use App\Models\Jugador;
use App\Models\Partido;
use App\Models\RegistroDeCambio;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Guarda el resultado de un partido tras validarlo contra su formato:
 * sets, ganador y estado (jugado, walkover, o pendiente de validación si
 * lo registra un jugador y el torneo exige que el rival lo confirme).
 * Recalcula la clasificación del grupo o avanza el cuadro.
 */
final class RegistrarResultado
{
    public function __construct(
        private readonly ValidadorDeResultado $validador = new ValidadorDeResultado,
        private readonly ConsolidarPartidoCerrado $consolidar = new ConsolidarPartidoCerrado,
    ) {}

    public function handle(Partido $partido, Resultado $resultado, User|Jugador $quien): Partido
    {
        if (! $partido->estado->abierto() || $partido->estado === EstadoPartido::PendienteValidacion) {
            throw new OperacionNoPermitida('El partido ya tiene un resultado. Anúlelo antes de registrar otro.');
        }

        if ($partido->local_id === null || $partido->visitante_id === null) {
            throw new OperacionNoPermitida('El partido aún no tiene a los dos participantes.');
        }

        $torneo = $partido->fase->categoria->torneo;
        $porJugador = $quien instanceof Jugador;

        if ($porJugador) {
            if ($torneo->resultados_por_jugadores === ResultadosPorJugadores::Desactivado) {
                throw new OperacionNoPermitida('En este torneo los resultados los registra la organización.');
            }

            if (! $this->juegaEn($quien, $partido)) {
                throw new OperacionNoPermitida('Solo los jugadores del partido pueden registrar su resultado.');
            }
        }

        $validacion = $this->validador->validar($resultado, $partido->formatoEfectivo()->aDto());

        if (! $validacion->valido) {
            throw new ResultadoInvalido($validacion->errores);
        }

        $guardado = DB::transaction(function () use ($partido, $resultado, $quien, $torneo, $porJugador, $validacion): Partido {
            $antes = $partido->only(['estado', 'ganador_id', 'especial']);

            $partido->sets()->delete();

            foreach ($resultado->sets as $i => $set) {
                $partido->sets()->create([
                    'numero' => $i + 1,
                    'juegos_local' => $set->juegosLocal,
                    'juegos_visitante' => $set->juegosVisitante,
                    'tie_break_local' => $set->tieBreakLocal,
                    'tie_break_visitante' => $set->tieBreakVisitante,
                    'es_super_tie_break' => $set->esSuperTieBreak,
                ]);
            }

            $pendienteDeValidar = $porJugador && $torneo->resultados_por_jugadores === ResultadosPorJugadores::ConValidacion;

            $partido->fill([
                'especial' => $resultado->especial,
                'ganador_id' => match ($validacion->ganaLocal) {
                    true => $partido->local_id,
                    false => $partido->visitante_id,
                    null => null,
                },
                'estado' => $pendienteDeValidar ? EstadoPartido::PendienteValidacion : ($resultado->especial !== null ? EstadoPartido::Walkover : EstadoPartido::Jugado),
                'registrado_en' => now(),
                'registrado_por_jugador_id' => $porJugador ? $quien->id : null,
                'validacion_vence_en' => $pendienteDeValidar ? now()->addMinutes($torneo->minutos_para_validar) : null,
                'validado_en' => $pendienteDeValidar ? null : now(),
                'validado_por' => $quien instanceof User ? $quien->id : null,
            ])->save();

            if (! $pendienteDeValidar) {
                $this->consolidar->handle($partido);
            }

            RegistroDeCambio::anotar('registrar_resultado', $partido, $quien, $antes, [
                ...$partido->only(['estado', 'ganador_id', 'especial']),
                'sets' => array_map(fn ($set): array => $set->toArray(), $resultado->sets),
            ]);

            return $partido->refresh();
        });

        if ($guardado->estado === EstadoPartido::PendienteValidacion) {
            app(Avisos::class)->resultadoPorValidar($guardado);
        } elseif ($guardado->cerrado()) {
            app(Avisos::class)->resultadoDelPartido($guardado);
        }

        return $guardado;
    }

    private function juegaEn(Jugador $jugador, Partido $partido): bool
    {
        return in_array($jugador->id, [...$partido->local->idsDeJugadores(), ...$partido->visitante->idsDeJugadores()], true);
    }
}
