<?php

namespace App\Filament\Resources\Torneos;

use App\Enums\Rol;
use App\Filament\Resources\Torneos\Pages\CreateTorneo;
use App\Filament\Resources\Torneos\Pages\EditTorneo;
use App\Filament\Resources\Torneos\Pages\ListTorneos;
use App\Filament\Resources\Torneos\Pages\PartidosDelTorneo;
use App\Filament\Resources\Torneos\RelationManagers\ArbitrosRelationManager;
use App\Filament\Resources\Torneos\RelationManagers\CategoriasRelationManager;
use App\Filament\Resources\Torneos\RelationManagers\PistasRelationManager;
use App\Filament\Resources\Torneos\Schemas\TorneoForm;
use App\Filament\Resources\Torneos\Tables\TorneosTable;
use App\Models\Torneo;
use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class TorneoResource extends Resource
{
    protected static ?string $model = Torneo::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static string|UnitEnum|null $navigationGroup = 'Competición';

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'torneos';

    protected static ?string $modelLabel = 'torneo';

    protected static ?string $pluralModelLabel = 'torneos';

    protected static ?string $recordTitleAttribute = 'nombre';

    /** Un administrador de torneo solo ve los suyos. */
    public static function getEloquentQuery(): Builder
    {
        $consulta = parent::getEloquentQuery();
        $usuario = Filament::auth()->user();

        if ($usuario instanceof User && $usuario->rol === Rol::Administrador) {
            $consulta->whereHas('administradores', fn (Builder $q) => $q->whereKey($usuario->id));
        }

        return $consulta;
    }

    public static function form(Schema $schema): Schema
    {
        return TorneoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TorneosTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            CategoriasRelationManager::class,
            PistasRelationManager::class,
            ArbitrosRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTorneos::route('/'),
            'create' => CreateTorneo::route('/nuevo'),
            'edit' => EditTorneo::route('/{record}/editar'),
            'partidos' => PartidosDelTorneo::route('/{record}/partidos'),
        ];
    }
}
