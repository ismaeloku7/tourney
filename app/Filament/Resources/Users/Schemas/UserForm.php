<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\Rol;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Password;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('nombre')->label('Nombre')->required()->maxLength(120),
                TextInput::make('apellidos')->label('Apellidos')->maxLength(160),
                TextInput::make('email')->label('Correo electrónico')->email()->required()->maxLength(190)->unique(ignoreRecord: true),
                TextInput::make('telefono')->label('Teléfono')->tel()->maxLength(30),
                Select::make('rol')->label('Rol')->options(Rol::opciones())->default(Rol::Administrador->value)->required()
                    ->helperText('El propietario lo puede todo; un administrador gestiona los torneos que se le asignen; un árbitro solo introduce resultados desde la mesa.'),
                TextInput::make('password')
                    ->label('Contraseña')
                    ->password()
                    ->revealable()
                    ->rule(Password::default())
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText('Al menos 12 caracteres. Al editar, déjela en blanco para no cambiarla.'),
                Toggle::make('activo')->label('Activo')->default(true)
                    ->disabled(fn (?User $record): bool => $record !== null && $record->is(auth()->user())),
            ]);
    }
}
