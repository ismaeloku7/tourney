<?php

namespace App\Filament\Resources\Jugadors;

use App\Filament\Resources\Jugadores\Pages\CreateJugador;
use App\Filament\Resources\Jugadores\Pages\EditJugador;
use App\Filament\Resources\Jugadores\Pages\ListJugadores;
use App\Filament\Resources\Jugadores\Schemas\JugadorForm;
use App\Filament\Resources\Jugadores\Tables\JugadoresTable;
use App\Models\Jugador;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class JugadorResource extends Resource
{
    protected static ?string $model = Jugador::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Competición';

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'jugadores';

    protected static ?string $modelLabel = 'jugador';

    protected static ?string $pluralModelLabel = 'jugadores';

    protected static ?string $recordTitleAttribute = 'nombre';

    public static function form(Schema $schema): Schema
    {
        return JugadorForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return JugadoresTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJugadores::route('/'),
            'create' => CreateJugador::route('/nuevo'),
            'edit' => EditJugador::route('/{record}/editar'),
        ];
    }
}
