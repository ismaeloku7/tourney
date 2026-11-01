<?php

namespace App\Filament\Resources\Torneos\RelationManagers;

use App\Enums\Genero;
use App\Enums\Modalidad;
use App\Models\Categoria;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Las categorías del torneo (Masculina 3ª, Mixta B…): cada una con su
 * cupo, su formato y su orden, que es también la prioridad del
 * planificador.
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
            ->components([
                TextInput::make('nombre')->label('Nombre')->required()->maxLength(80),
                Select::make('genero')->label('Género')->options(Genero::opciones())->default(Genero::Abierto->value)->required(),
                Select::make('modalidad')->label('Modalidad')->options(Modalidad::opciones())->default(Modalidad::Parejas->value)->required()
                    ->helperText('La v1 juega por parejas.'),
                TextInput::make('nivel')->label('Nivel')->maxLength(40)->helperText('3ª, 4ª, B… solo informativo.'),
                TextInput::make('cupo')->label('Cupo de parejas')->integer()->minValue(2)->maxValue(500)->helperText('En blanco, sin límite.'),
                TextInput::make('minimo_participantes')->label('Mínimo para celebrarse')->integer()->minValue(0)->default(0),
                TextInput::make('precio')->label('Precio por pareja')->numeric()->minValue(0)->suffix('€')->helperText('Informativo.'),
                Select::make('formato_partido_id')->label('Formato de partido')->relationship('formatoPartido', 'nombre')->placeholder('El predeterminado del organizador'),
                TextInput::make('orden')->label('Orden')->integer()->minValue(0)->default(1)->helperText('También es la prioridad al planificar horarios.'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('orden')
            ->recordTitleAttribute('nombre')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('participantes'))
            ->columns([
                TextColumn::make('orden')->label('Orden'),
                TextColumn::make('nombre')->label('Categoría'),
                TextColumn::make('genero')->label('Género')->badge()->formatStateUsing(fn (Genero $state): string => $state->etiqueta()),
                TextColumn::make('nivel')->label('Nivel')->placeholder('—'),
                TextColumn::make('participantes_count')->label('Parejas')->state(fn (Categoria $record): string => $record->getAttribute('participantes_count').($record->cupo !== null ? ' / '.$record->cupo : '')),
                TextColumn::make('formatoPartido.nombre')->label('Formato')->placeholder('Predeterminado'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
