<?php

namespace App\Filament\Resources\Pistas\Pages;

use App\Filament\Resources\Pistas\PistaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPistas extends ListRecords
{
    protected static string $resource = PistaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
