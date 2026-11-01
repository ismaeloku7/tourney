<?php

namespace App\Filament\Resources\Categorias\Tables;

use App\Enums\EstadoInscripcion;
use App\Enums\Genero;
use App\Models\Categoria;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Todas las categorías de todos los torneos: un atajo, sin alta (se crean en el torneo). */
class CategoriasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with('torneo')
                ->withCount(['fases', 'inscripciones as confirmadas_count' => fn (Builder $q) => $q->where('estado', EstadoInscripcion::Confirmada->value)]))
            ->columns([
                TextColumn::make('torneo.nombre')->label('Torneo')->sortable()->searchable(),
                TextColumn::make('nombre')->label('Categoría')->searchable(),
                TextColumn::make('genero')->label('Género')->badge()->formatStateUsing(fn (Genero $state): string => $state->etiqueta()),
                TextColumn::make('confirmadas_count')->label('Parejas')->state(fn (Categoria $record): string => $record->getAttribute('confirmadas_count').($record->cupo !== null ? ' / '.$record->cupo : '')),
                TextColumn::make('fases_count')->label('Fases'),
            ])
            ->filters([
                SelectFilter::make('torneo_id')->label('Torneo')->relationship('torneo', 'nombre'),
            ])
            ->recordActions([
                EditAction::make()->label('Configurar'),
            ]);
    }
}
