<?php

namespace App\Filament\Resources\Pistas\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PistasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('orden')
            ->columns([
                TextColumn::make('orden')->label('Orden')->sortable(),
                TextColumn::make('nombre')->label('Pista')->searchable(),
                TextColumn::make('sede.nombre')->label('Sede')->placeholder('—'),
                IconColumn::make('activa')->label('Activa')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
