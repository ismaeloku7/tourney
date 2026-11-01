<?php

namespace App\Filament\Resources\FormatosPartido;

use App\Filament\Resources\FormatosPartido\Pages\CreateFormatoPartido;
use App\Filament\Resources\FormatosPartido\Pages\EditFormatoPartido;
use App\Filament\Resources\FormatosPartido\Pages\ListFormatosPartido;
use App\Filament\Resources\FormatosPartido\Schemas\FormatoPartidoForm;
use App\Filament\Resources\FormatosPartido\Tables\FormatosPartidoTable;
use App\Models\FormatoPartido;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class FormatoPartidoResource extends Resource
{
    protected static ?string $model = FormatoPartido::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'Instalaciones';

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'formatos-de-partido';

    protected static ?string $modelLabel = 'formato de partido';

    protected static ?string $pluralModelLabel = 'formatos de partido';

    protected static ?string $recordTitleAttribute = 'nombre';

    public static function form(Schema $schema): Schema
    {
        return FormatoPartidoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FormatosPartidoTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFormatosPartido::route('/'),
            'create' => CreateFormatoPartido::route('/nuevo'),
            'edit' => EditFormatoPartido::route('/{record}/editar'),
        ];
    }
}
