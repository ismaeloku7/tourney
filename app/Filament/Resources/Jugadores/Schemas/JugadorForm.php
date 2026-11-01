<?php

namespace App\Filament\Resources\Jugadores\Schemas;

use App\Enums\Genero;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class JugadorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('nombre')->label('Nombre')->required()->maxLength(80),
                TextInput::make('apellidos')->label('Apellidos')->required()->maxLength(120),
                TextInput::make('email')->label('Correo electrónico')->email()->maxLength(190)->unique(ignoreRecord: true)->helperText('Nunca sale en público.'),
                TextInput::make('telefono')->label('Teléfono')->tel()->maxLength(30)->helperText('Nunca sale en público.'),
                Select::make('genero')->label('Género')->options(Genero::opciones())->placeholder('Sin indicar'),
                TextInput::make('nivel_declarado')->label('Nivel')->maxLength(40),
                DatePicker::make('fecha_nacimiento')->label('Fecha de nacimiento')->native(false)->displayFormat('d/m/Y')->maxDate(now())->helperText('Opcional. Un menor de 14 años necesita autorización de su tutor.'),
                TextInput::make('alias')->label('Alias')->maxLength(60)->helperText('Lo que se enseña en público si el torneo activa la privacidad.'),
            ]);
    }
}
