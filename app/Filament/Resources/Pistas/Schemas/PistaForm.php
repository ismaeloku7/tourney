<?php

namespace App\Filament\Resources\Pistas\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PistaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nombre')->label('Nombre')->required()->maxLength(60)->helperText('Pista 1, Central, Cristal…'),
            Select::make('sede_id')->label('Sede')->relationship('sede', 'nombre')->placeholder('Sin sede'),
            TextInput::make('orden')->label('Orden')->integer()->minValue(0)->default(0),
            Toggle::make('activa')->label('Activa')->default(true)->helperText('Una pista inactiva no se ofrece a los torneos nuevos.'),
        ]);
    }
}
