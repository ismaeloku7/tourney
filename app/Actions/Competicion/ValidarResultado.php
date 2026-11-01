<?php

namespace App\Actions\Competicion;

use App\Competicion\Errores\OperacionNoPermitida;
use App\Enums\EstadoPartido;
use App\Models\Jugador;
use App\Models\Partido;
use App\Models\RegistroDeCambio;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Da por bueno un resultado pendiente de validación: lo hace el rival
 * (no quien lo registró) o la organización. Entonces cuenta: clasificación
 * y cuadro.
 */
final class ValidarResultado
{
    public function __construct(private readonly ConsolidarPartidoCerrado $consolidar = new ConsolidarPartidoCerrado) {}

    /** Sin `$quien`, la valida el sistema al vencer el plazo (resultados:validar-vencidos). */
    public function handle(Partido $partido, User|Jugador|null $quien): Partido
    {
        if ($partido->estado !== EstadoPartido::PendienteValidacion) {
            throw new OperacionNoPermitida('El partido no está pendiente de validación.');
        }

        if ($quien instanceof Jugador) {
            $registrador = $partido->registrado_por_jugador_id;
            $ladoDelRegistrador = $registrador !== null && in_array($registrador, $partido->local->idsDeJugadores(), true) ? $partido->local : $partido->visitante;
            $rival = $ladoDelRegistrador->is($partido->local) ? $partido->visitante : $partido->local;

            if (! in_array($quien->id, $rival->idsDeJugadores(), true)) {
                throw new OperacionNoPermitida('Solo el rival puede validar el resultado.');
            }
        }

        return DB::transaction(function () use ($partido, $quien): Partido {
            $partido->fill([
                'estado' => $partido->especial !== null ? EstadoPartido::Walkover : EstadoPartido::Jugado,
                'validado_en' => now(),
                'validado_por' => $quien instanceof User ? $quien->id : null,
                'validacion_vence_en' => null,
            ])->save();

            $this->consolidar->handle($partido);

            RegistroDeCambio::anotar('validar_resultado', $partido, $quien, ['estado' => EstadoPartido::PendienteValidacion->value], ['estado' => $partido->estado->value]);

            return $partido->refresh();
        });
    }
}
