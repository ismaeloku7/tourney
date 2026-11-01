<?php

namespace App\Filament\Resources\Torneos\Pages;

use App\Filament\Resources\Torneos\TorneoResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateTorneo extends CreateRecord
{
    protected static string $resource = TorneoResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['creado_por'] = Filament::auth()->id();

        return $data;
    }
}
