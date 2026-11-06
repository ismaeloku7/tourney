<?php

namespace App\Filament\Resources\Torneos\Pages;

use App\Filament\Resources\Torneos\Pages\Concerns\BarraDelTorneo;
use App\Filament\Resources\Torneos\TorneoResource;
use App\Models\Torneo;
use Filament\Resources\Pages\EditRecord;

/**
 * Editar los datos del torneo. Arriba, la barra de siempre del torneo; al
 * guardar se vuelve a la ficha.
 */
class EditTorneo extends EditRecord
{
    use BarraDelTorneo;

    protected static string $resource = TorneoResource::class;

    protected function getHeaderActions(): array
    {
        return $this->barraDelTorneo('editar');
    }

    protected function getRedirectUrl(): string
    {
        return ViewTorneo::getUrl(['record' => $this->torneo()]);
    }

    private function torneo(): Torneo
    {
        $torneo = $this->getRecord();
        assert($torneo instanceof Torneo);

        return $torneo;
    }
}
