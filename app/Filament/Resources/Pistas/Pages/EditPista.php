<?php

namespace App\Filament\Resources\Pistas\Pages;

use App\Filament\Resources\Pistas\PistaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPista extends EditRecord
{
    protected static string $resource = PistaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
