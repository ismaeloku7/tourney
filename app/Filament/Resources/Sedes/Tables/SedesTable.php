<?php

namespace App\Filament\Resources\Sedes\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SedesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('orden')
            ->columns([
                TextColumn::make('orden')->label('Orden')->sortable(),
                TextColumn::make('nombre')->label('Nombre')->searchable(),
                TextColumn::make('direccion')->label('Dirección')->placeholder('—'),
                TextColumn::make('pistas_count')->label('Pistas')->counts('pistas'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
