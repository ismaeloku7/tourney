<?php

namespace App\Filament\Resources\Sedes;

use App\Filament\Resources\Sedes\Pages\CreateSede;
use App\Filament\Resources\Sedes\Pages\EditSede;
use App\Filament\Resources\Sedes\Pages\ListSedes;
use App\Filament\Resources\Sedes\Schemas\SedeForm;
use App\Filament\Resources\Sedes\Tables\SedesTable;
use App\Models\Sede;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class SedeResource extends Resource
{
    protected static ?string $model = Sede::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice;

    protected static string|UnitEnum|null $navigationGroup = 'Instalaciones';

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'sedes';

    protected static ?string $modelLabel = 'sede';

    protected static ?string $pluralModelLabel = 'sedes';

    protected static ?string $recordTitleAttribute = 'nombre';

    public static function form(Schema $schema): Schema
    {
        return SedeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SedesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSedes::route('/'),
            'create' => CreateSede::route('/nuevo'),
            'edit' => EditSede::route('/{record}/editar'),
        ];
    }
}
