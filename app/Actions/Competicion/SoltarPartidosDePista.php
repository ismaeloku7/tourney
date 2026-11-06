<?php

namespace App\Actions\Competicion;

use App\Enums\EstadoPartido;
use App\Models\Franja;
use App\Models\Partido;
use App\Models\RegistroDeCambio;
use App\Models\Torneo;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cuando una pista deja de ser del torneo, lo que tenía colocado y aún no
 * se ha jugado vuelve a quedar sin hora ni pista, para que el siguiente
 * «Calcular» lo coloque en las pistas que quedan; sus franjas se borran.
 * Lo ya jugado o en juego conserva dónde se jugó. Devuelve cuántos
 * partidos se han soltado.
 */
final class SoltarPartidosDePista
{
    public function handle(Torneo $torneo, int $pistaId, User $usuario): int
    {
        return DB::transaction(function () use ($torneo, $pistaId, $usuario): int {
            $partidos = self::colocadosEn($torneo, $pistaId)->get();

            foreach ($partidos as $partido) {
                $partido->forceFill([
                    'franja_id' => null,
                    'pista_id' => null,
                    'estado' => $partido->estado === EstadoPartido::Programado ? EstadoPartido::Pendiente : $partido->estado,
                    'aviso_siguientes_en' => null,
                    'aviso_calentar_en' => null,
                ])->save();
            }

            Franja::query()->where('torneo_id', $torneo->id)->where('pista_id', $pistaId)->whereDoesntHave('partido')->delete();

            if ($partidos->isNotEmpty()) {
                RegistroDeCambio::anotar('soltar_partidos_de_pista', $torneo, $usuario, null, ['pista' => $pistaId, 'partidos' => $partidos->pluck('id')->all()]);
            }

            return $partidos->count();
        });
    }

    /** Suelta los partidos de todas las pistas que ya no son del torneo. */
    public function huerfanos(Torneo $torneo, User $usuario): int
    {
        $soltados = 0;
        foreach (self::pistasQuitadas($torneo) as $pistaId) {
            $soltados += $this->handle($torneo, $pistaId, $usuario);
        }

        return $soltados;
    }

    /**
     * Los partidos sin jugar que siguen colocados en una pista que ya no
     * es del torneo.
     *
     * @return Collection<int, Partido>
     */
    public static function enPistasQuitadas(Torneo $torneo): Collection
    {
        $pistas = self::pistasQuitadas($torneo);

        return $pistas === []
            ? new Collection
            : self::sinJugar($torneo)->whereIn('partidos.pista_id', $pistas)->get();
    }

    /** @return list<int> */
    private static function pistasQuitadas(Torneo $torneo): array
    {
        $delTorneo = $torneo->torneoPistas()->pluck('pista_id')->map(fn (mixed $id): int => (int) $id)->all();

        return array_values(array_diff(
            self::sinJugar($torneo)->whereNotNull('partidos.pista_id')->distinct()->pluck('partidos.pista_id')->map(fn (mixed $id): int => (int) $id)->all(),
            $delTorneo,
        ));
    }

    /** @return Builder<Partido> */
    private static function colocadosEn(Torneo $torneo, int $pistaId): Builder
    {
        return self::sinJugar($torneo)->where(fn (Builder $q) => $q
            ->where('partidos.pista_id', $pistaId)
            ->orWhereHas('franja', fn (Builder $f) => $f->where('pista_id', $pistaId)));
    }

    /** @return Builder<Partido> */
    private static function sinJugar(Torneo $torneo): Builder
    {
        return Partido::query()
            ->whereHas('fase.categoria', fn (Builder $q) => $q->where('torneo_id', $torneo->id))
            ->whereIn('partidos.estado', [EstadoPartido::Pendiente->value, EstadoPartido::Programado->value]);
    }
}
