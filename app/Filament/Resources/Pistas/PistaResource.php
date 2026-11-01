<?php

namespace App\Filament\Resources\Pistas;

use App\Filament\Resources\Pistas\Pages\CreatePista;
use App\Filament\Resources\Pistas\Pages\EditPista;
use App\Filament\Resources\Pistas\Pages\ListPistas;
use App\Filament\Resources\Pistas\Schemas\PistaForm;
use App\Filament\Resources\Pistas\Tables\PistasTable;
use App\Models\Pista;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PistaResource extends Resource
{
    protected static ?string $model = Pista::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = 'Instalaciones';

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'pistas';

    protected static ?string $modelLabel = 'pista';

    protected static ?string $pluralModelLabel = 'pistas';

    protected static ?string $recordTitleAttribute = 'nombre';

    public static function form(Schema $schema): Schema
    {
        return PistaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PistasTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPistas::route('/'),
            'create' => CreatePista::route('/nuevo'),
            'edit' => EditPista::route('/{record}/editar'),
        ];
    }
}
