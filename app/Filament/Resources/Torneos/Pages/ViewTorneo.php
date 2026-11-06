<?php

namespace App\Filament\Resources\Torneos\Pages;

use App\Filament\Resources\Torneos\Pages\Concerns\BarraDelTorneo;
use App\Filament\Resources\Torneos\TorneoResource;
use App\Models\Torneo;
use Filament\Resources\Pages\ViewRecord;

/**
 * La ficha del torneo: sus datos y reglas, solo para leer (se cambian con
 * «Editar»), y debajo sus categorías, parejas, pistas y equipo.
 */
class ViewTorneo extends ViewRecord
{
    use BarraDelTorneo;

    protected static string $resource = TorneoResource::class;

    protected function getHeaderActions(): array
    {
        return $this->barraDelTorneo('ficha');
    }

    private function torneo(): Torneo
    {
        $torneo = $this->getRecord();
        assert($torneo instanceof Torneo);

        return $torneo;
    }
}
