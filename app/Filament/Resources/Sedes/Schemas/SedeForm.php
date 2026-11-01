<?php

namespace App\Filament\Resources\Sedes\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SedeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nombre')->label('Nombre')->required()->maxLength(120),
            TextInput::make('direccion')->label('Dirección')->maxLength(255),
            TextInput::make('mapa_url')->label('Enlace al mapa')->url()->maxLength(500)->helperText('Un enlace de Google Maps o similar, para la página del torneo.'),
            TextInput::make('orden')->label('Orden')->integer()->minValue(0)->default(0),
        ]);
    }
}
