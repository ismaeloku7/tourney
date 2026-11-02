<?php

namespace App\Filament\Resources\Parejas\Pages;

use App\Filament\Resources\Parejas\ParejaResource;
use Filament\Resources\Pages\ListRecords;

class ListParejas extends ListRecords
{
    protected static string $resource = ParejaResource::class;

    public function getSubheading(): string
    {
        return 'Las parejas de todos los torneos. Para confirmarlas, completarlas o darlas de baja, «Gestionar» lleva a su categoría.';
    }
}
