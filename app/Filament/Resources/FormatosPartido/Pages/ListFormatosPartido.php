<?php

namespace App\Filament\Resources\FormatosPartido\Pages;

use App\Filament\Resources\FormatosPartido\FormatoPartidoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFormatosPartido extends ListRecords
{
    protected static string $resource = FormatoPartidoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
