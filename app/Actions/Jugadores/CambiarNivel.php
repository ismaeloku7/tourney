<?php

namespace App\Actions\Jugadores;

use App\Competicion\Errores\OperacionNoPermitida;
use App\Enums\EstadoInscripcion;
use App\Enums\EstadoTorneo;
use App\Models\Jugador;
use App\Models\Participante;
use App\Models\RegistroDeCambio;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * El jugador cambia su nivel desde el portal, y también el de su
 * compañero de pareja (sea o no el capitán), mientras la pareja siga
 * inscrita en un torneo que no ha terminado.
 */
final class CambiarNivel
{
    public function handle(Jugador $quien, Jugador $de, ?string $nivel): Jugador
    {
        if (! self::puede($quien, $de)) {
            throw new OperacionNoPermitida('Solo puedes cambiar tu nivel y el de tu compañero de pareja.');
        }

        $nivel = trim((string) $nivel);

        if ($nivel !== '' && (preg_match('/^\d{1,2}([.,]\d{1,2})?$/', $nivel) !== 1 || (float) str_replace(',', '.', $nivel) > 10)) {
            throw new OperacionNoPermitida('El nivel es un número del 0 al 10, como 3 o 2,5.');
        }

        $antes = $de->nivel_declarado;
        $de->update(['nivel_declarado' => $nivel !== '' ? $nivel : null]);

        if ($antes !== $de->nivel_declarado) {
            RegistroDeCambio::anotar('cambiar_nivel', $de, $quien, ['nivel' => $antes], ['nivel' => $de->nivel_declarado]);
        }

        return $de;
    }

    public static function puede(Jugador $quien, Jugador $de): bool
    {
        return $quien->is($de) || self::companeros($quien)->contains(fn (Jugador $j): bool => $j->is($de));
    }

    /**
     * Sus compañeros en las parejas que siguen inscritas en torneos que no
     * han terminado.
     *
     * @return Collection<int, Jugador>
     */
    public static function companeros(Jugador $jugador): Collection
    {
        $parejas = Participante::query()
            ->whereHas('participanteJugadores', fn (Builder $q) => $q->where('jugador_id', $jugador->id))
            ->whereHas('inscripcion', fn (Builder $q) => $q->where('estado', '!=', EstadoInscripcion::Baja->value))
            ->whereHas('categoria.torneo', fn (Builder $q) => $q->whereNotIn('estado', [EstadoTorneo::Finalizado->value, EstadoTorneo::Archivado->value]))
            ->pluck('id');

        return Jugador::query()
            ->whereHas('participantes', fn (Builder $q) => $q->whereIn('participantes.id', $parejas))
            ->whereKeyNot($jugador->id)
            ->orderBy('nombre')
            ->get();
    }
}
