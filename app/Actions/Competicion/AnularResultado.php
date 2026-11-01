<?php

namespace App\Actions\Competicion;

use App\Competicion\AvanceDeCuadro;
use App\Competicion\Errores\OperacionNoPermitida;
use App\Enums\EstadoPartido;
use App\Models\Partido;
use App\Models\RegistroDeCambio;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Deja un partido sin resultado: borra sus sets, vuelve a programado o
 * pendiente, recalcula la clasificación y, en un cuadro, vacía los huecos
 * siguientes (lanza AvanceNoReversible si el siguiente ya se jugó).
 */
final class AnularResultado
{
    public function __construct(
        private readonly AvanceDeCuadro $avance = new AvanceDeCuadro,
        private readonly Cuadros $cuadros = new Cuadros,
        private readonly ConsolidarPartidoCerrado $consolidar = new ConsolidarPartidoCerrado,
    ) {}

    public function handle(Partido $partido, User $usuario): Partido
    {
        if (! $partido->cerrado() && $partido->estado !== EstadoPartido::PendienteValidacion) {
            throw new OperacionNoPermitida('El partido no tiene resultado que anular.');
        }

        return DB::transaction(function () use ($partido, $usuario): Partido {
            $antes = ['estado' => $partido->estado->value, 'ganador_id' => $partido->ganador_id, 'sets' => $partido->sets->map(fn ($s) => $s->only(['juegos_local', 'juegos_visitante']))->all()];

            if ($partido->fase->tipo->esDeCuadro() && $partido->cerrado()) {
                $cuadro = $this->cuadros->cargar($partido->fase);
                $this->cuadros->guardar($this->avance->revertir($cuadro, $partido->aDto()));
                $partido->refresh();
            }

            $partido->sets()->delete();
            $partido->fill([
                'estado' => $partido->franja_id !== null ? EstadoPartido::Programado : EstadoPartido::Pendiente,
                'especial' => null,
                'ganador_id' => null,
                'registrado_en' => null,
                'registrado_por_jugador_id' => null,
                'validacion_vence_en' => null,
                'validado_en' => null,
                'validado_por' => null,
            ])->save();

            $this->consolidar->handle($partido);

            RegistroDeCambio::anotar('anular_resultado', $partido, $usuario, $antes, ['estado' => $partido->estado->value]);

            return $partido->refresh();
        });
    }
}
