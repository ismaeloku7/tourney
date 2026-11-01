<?php

namespace App\Actions\Inscripciones;

use App\Competicion\Errores\OperacionNoPermitida;
use App\Enums\EstadoInscripcion;
use App\Models\Categoria;
use App\Models\Inscripcion;
use App\Models\Torneo;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Confirma de una vez las parejas pendientes que ya tienen sus dos
 * jugadores, del torneo entero o de una categoría. Van por orden de
 * llegada; las que no caben en el cupo se quedan pendientes. Las que
 * esperan compañero no se tocan.
 */
final class ConfirmarParejasCompletas
{
    public function __construct(private readonly CambiarEstadoDeInscripcion $cambiarEstado) {}

    /** @return array{confirmadas: int, sin_plaza: int, sin_companero: int} */
    public function handle(Torneo $torneo, ?Categoria $categoria = null, ?User $usuario = null, bool $avisar = false): array
    {
        $completas = self::pendientes($torneo, $categoria)
            ->whereHas('participante', fn (Builder $q) => $q->has('participanteJugadores', '>=', 2))
            ->with('categoria', 'participante')
            ->orderBy('id')
            ->get();

        $confirmadas = 0;
        $sinPlaza = 0;
        foreach ($completas as $inscripcion) {
            try {
                $this->cambiarEstado->handle($inscripcion, EstadoInscripcion::Confirmada, $usuario, $avisar);
                $confirmadas++;
            } catch (OperacionNoPermitida) {
                $sinPlaza++;
            }
        }

        return [
            'confirmadas' => $confirmadas,
            'sin_plaza' => $sinPlaza,
            'sin_companero' => self::pendientes($torneo, $categoria)->count() - $sinPlaza,
        ];
    }

    /** @return Builder<Inscripcion> */
    private static function pendientes(Torneo $torneo, ?Categoria $categoria): Builder
    {
        return Inscripcion::query()
            ->whereIn('categoria_id', $categoria !== null ? [$categoria->id] : $torneo->categorias()->pluck('id')->all())
            ->where('estado', EstadoInscripcion::Pendiente->value);
    }
}
