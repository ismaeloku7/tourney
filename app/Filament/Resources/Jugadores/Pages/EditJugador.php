<?php

namespace App\Filament\Resources\Jugadores\Pages;

use App\Filament\Resources\Jugadores\JugadorResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditJugador extends EditRecord
{
    protected static string $resource = JugadorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
