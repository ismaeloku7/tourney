<?php

namespace App\Filament\Competicion;

use App\Competicion\Datos\Origen;
use App\Enums\TipoDeOrigen;
use App\Models\Grupo;
use App\Models\Partido;
use App\Models\Set;

/** Cómo se escriben en el panel un resultado, un hueco del cuadro y una ronda. */
final class Marcador
{
    /** «6-3 6-4», «6-3 6-7(5) [10-7]», «Walkover (García / López)» o «—». */
    public static function resultado(Partido $partido): string
    {
        if ($partido->especial !== null) {
            $ganador = $partido->ganador?->nombre;

            return $partido->especial->etiqueta().($ganador !== null ? " ({$ganador})" : '');
        }

        if ($partido->sets->isEmpty()) {
            return '—';
        }

        return $partido->sets->map(fn (Set $set): string => $set->es_super_tie_break
            ? "[{$set->juegos_local}-{$set->juegos_visitante}]"
            : "{$set->juegos_local}-{$set->juegos_visitante}".($set->tie_break_local !== null && $set->tie_break_visitante !== null ? '('.min($set->tie_break_local, $set->tie_break_visitante).')' : ''))
            ->implode(' ');
    }

    /** El nombre del participante o, si aún no se sabe, de dónde saldrá. */
    public static function lado(Partido $partido, bool $local): string
    {
        $participante = $local ? $partido->local : $partido->visitante;

        if ($participante !== null) {
            return $participante->nombre;
        }

        $origen = $local ? $partido->origenLocalDto() : $partido->origenVisitanteDto();

        if ($origen === null) {
            return 'Bye';
        }

        return self::origen($origen);
    }

    public static function origen(Origen $origen): string
    {
        $grupo = $origen->tipo === TipoDeOrigen::PosicionDeGrupo ? Grupo::query()->find($origen->referencia)?->nombre : null;

        return $origen->etiqueta($grupo);
    }

    /** «Final», «Semifinal», «Cuartos», «Octavos», «1ª ronda»… según las rondas del cuadro. */
    public static function ronda(int $ronda, int $rondas, bool $tercerPuesto = false): string
    {
        if ($tercerPuesto) {
            return 'Tercer puesto';
        }

        return match ($rondas - $ronda) {
            0 => 'Final',
            1 => 'Semifinales',
            2 => 'Cuartos de final',
            3 => 'Octavos de final',
            default => "{$ronda}ª ronda",
        };
    }
}
