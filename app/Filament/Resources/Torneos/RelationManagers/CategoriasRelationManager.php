<?php

namespace App\Filament\Resources\Torneos\RelationManagers;

use App\Enums\EstadoInscripcion;
use App\Enums\Genero;
use App\Filament\Resources\Categorias\CategoriaResource;
use App\Filament\Resources\Categorias\Schemas\CategoriaForm;
use App\Models\Categoria;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Las categorías del torneo (Masculina 3ª, Mixta B…): cada una con su
 * cupo, su formato y su orden, que es también la prioridad del
 * planificador. Las fases y las inscripciones se llevan desde su ficha
 * («Configurar»).
 */
class CategoriasRelationManager extends RelationManager
{
    protected static string $relationship = 'categorias';

    protected static ?string $title = 'Categorías';

    protected static ?string $modelLabel = 'categoría';

    protected static ?string $pluralModelLabel = 'categorías';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components(CategoriaForm::campos());
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('orden')
            ->recordTitleAttribute('nombre')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount(['fases', 'inscripciones as confirmadas_count' => fn (Builder $q) => $q->where('estado', EstadoInscripcion::Confirmada->value)]))
            ->columns([
                TextColumn::make('orden')->label('Orden'),
                TextColumn::make('nombre')->label('Categoría'),
                TextColumn::make('genero')->label('Género')->badge()->formatStateUsing(fn (Genero $state): string => $state->etiqueta()),
                TextColumn::make('nivel')->label('Nivel')->placeholder('—'),
                TextColumn::make('confirmadas_count')->label('Parejas')->state(fn (Categoria $record): string => $record->getAttribute('confirmadas_count').($record->cupo !== null ? ' / '.$record->cupo : '')),
                TextColumn::make('fases_count')->label('Fases'),
                TextColumn::make('formatoPartido.nombre')->label('Formato')->placeholder('Predeterminado'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                Action::make('configurar')
                    ->label('Configurar')
                    ->icon(Heroicon::OutlinedCog6Tooth)
                    ->url(fn (Categoria $record): string => CategoriaResource::getUrl('edit', ['record' => $record])),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
