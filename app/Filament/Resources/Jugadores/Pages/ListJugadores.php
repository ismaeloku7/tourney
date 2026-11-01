<?php

namespace App\Filament\Resources\Jugadores\Pages;

use App\Filament\Resources\Jugadores\JugadorResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListJugadores extends ListRecords
{
    protected static string $resource = JugadorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
