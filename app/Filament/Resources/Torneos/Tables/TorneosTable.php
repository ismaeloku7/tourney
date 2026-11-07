<?php

namespace App\Filament\Resources\Torneos\Tables;

use App\Enums\EstadoTorneo;
use App\Filament\Resources\Torneos\TorneoResource;
use App\Models\Torneo;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TorneosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('fecha_inicio', 'desc')
            ->columns([
                TextColumn::make('nombre')
                    ->label('Torneo')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Torneo $record): string => '/torneos/'.$record->slug),
                TextColumn::make('fecha_inicio')
                    ->label('Fechas')
                    ->date('d/m/Y')
                    ->sortable()
                    ->description(fn (Torneo $record): string => 'hasta '.$record->fecha_fin->format('d/m/Y')),
                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (EstadoTorneo $state): string => $state->etiqueta())
                    ->color(fn (EstadoTorneo $state): string => $state->color()),
                TextColumn::make('categorias_count')
                    ->label('Categorías')
                    ->counts('categorias'),
                TextColumn::make('sede.nombre')
                    ->label('Sede')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('estado')
                    ->label('Estado')
                    ->options(EstadoTorneo::opciones()),
            ])
            // Al pulsar un torneo se abre su ficha; desde ella (o desde aquí) se edita.
            // Un árbitro va a sus partidos: la ficha no es para él.
            ->recordUrl(fn (Torneo $record): string => TorneoResource::getUrl(TorneoResource::esArbitro() ? 'partidos' : 'view', ['record' => $record]))
            ->recordActions([
                ViewAction::make()->label('Ficha')->hidden(fn (): bool => TorneoResource::esArbitro()),
                EditAction::make(),
            ]);
    }
}
