<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\Rol;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('nombre')
            ->columns([
                TextColumn::make('nombre')->label('Nombre')->formatStateUsing(fn (User $record): string => $record->nombre_completo)->searchable(['nombre', 'apellidos'])->sortable(),
                TextColumn::make('email')->label('Correo electrónico')->searchable(),
                TextColumn::make('rol')->label('Rol')->badge()->formatStateUsing(fn (Rol $state): string => $state->etiqueta()),
                IconColumn::make('activo')->label('Activo')->boolean(),
                TextColumn::make('ultimo_acceso')->label('Última vez que entró')->since()->placeholder('Nunca ha entrado')->sortable(),
            ])
            ->filters([
                SelectFilter::make('rol')->label('Rol')->options(Rol::opciones()),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
