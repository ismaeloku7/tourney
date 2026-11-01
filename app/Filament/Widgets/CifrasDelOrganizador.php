<?php

namespace App\Filament\Widgets;

use App\Enums\EstadoInscripcion;
use App\Enums\EstadoPartido;
use App\Enums\EstadoTorneo;
use App\Models\Inscripcion;
use App\Models\Jugador;
use App\Models\Partido;
use App\Models\Torneo;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Las cuatro cifras de arriba del inicio. */
class CifrasDelOrganizador extends StatsOverviewWidget
{
    protected static ?int $sort = -2;

    protected function getPollingInterval(): ?string
    {
        return null;
    }

    /** @return array<int, Stat> */
    protected function getStats(): array
    {
        $activos = Torneo::query()->whereIn('estado', [EstadoTorneo::InscripcionAbierta->value, EstadoTorneo::InscripcionCerrada->value, EstadoTorneo::Sorteo->value, EstadoTorneo::EnJuego->value])->count();
        $inscripcionesHoy = Inscripcion::query()->where('estado', EstadoInscripcion::Confirmada->value)->whereDate('confirmada_en', today())->count();
        $pendientes = Partido::query()->whereIn('estado', [EstadoPartido::Pendiente->value, EstadoPartido::Programado->value])->whereNotNull('local_id')->whereNotNull('visitante_id')->count();
        $jugadores = Jugador::query()->count();

        return [
            Stat::make('Torneos activos', $activos)->description('Abiertos, en sorteo o en juego'),
            Stat::make('Inscripciones de hoy', $inscripcionesHoy)->description('Confirmadas hoy'),
            Stat::make('Partidos pendientes', $pendientes)->description('Con los dos participantes y sin resultado'),
            Stat::make('Jugadores', $jugadores)->description('En la base del organizador'),
        ];
    }
}
