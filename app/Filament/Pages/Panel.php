<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\CifrasDelOrganizador;
use App\Filament\Widgets\ProximosTorneos;
use Filament\Pages\Dashboard;

/**
 * El inicio del panel: las cifras del organizador y sus próximos torneos.
 */
class Panel extends Dashboard
{
    protected static ?string $title = 'Panel';

    protected static ?string $navigationLabel = 'Panel';

    /** @return array<class-string> */
    public function getWidgets(): array
    {
        return [
            CifrasDelOrganizador::class,
            ProximosTorneos::class,
        ];
    }
}
