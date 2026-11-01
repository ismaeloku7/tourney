<?php

namespace App\Filament\Resources\Jugadores\Tables;

use App\Enums\Genero;
use App\Models\Jugador;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class JugadoresTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('apellidos')
            ->columns([
                TextColumn::make('apellidos')->label('Jugador')->formatStateUsing(fn (Jugador $record): string => $record->nombre_completo)->searchable(['nombre', 'apellidos'])->sortable(),
                TextColumn::make('email')->label('Correo')->searchable()->placeholder('—'),
                TextColumn::make('telefono')->label('Teléfono')->placeholder('—'),
                TextColumn::make('genero')->label('Género')->badge()->formatStateUsing(fn (Genero $state): string => $state->etiqueta())->placeholder('—'),
                TextColumn::make('nivel_declarado')->label('Nivel')->placeholder('—'),
                TextColumn::make('participantes_count')->label('Torneos')->counts('participantes'),
            ])
            ->filters([
                SelectFilter::make('genero')->label('Género')->options(Genero::opciones()),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
