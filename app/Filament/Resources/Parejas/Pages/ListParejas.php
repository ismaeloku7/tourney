<?php

namespace App\Filament\Resources\Parejas\Pages;

use App\Filament\Resources\Parejas\ConVistaParaCopiar;
use App\Filament\Resources\Parejas\ParejaResource;
use App\Filament\Resources\Parejas\SeCopiaParaPegar;
use Filament\Resources\Pages\ListRecords;

class ListParejas extends ListRecords implements SeCopiaParaPegar
{
    use ConVistaParaCopiar;

    protected static string $resource = ParejaResource::class;

    public function getSubheading(): string
    {
        return 'Las parejas de todos los torneos. Pulse en una para editarla: nombre, estado, pago, notas y los datos de sus jugadores.';
    }
}
