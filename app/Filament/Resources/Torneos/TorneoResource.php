<?php

namespace App\Filament\Resources\Torneos;

use App\Enums\Rol;
use App\Filament\Resources\Torneos\Pages\CompeticionDelTorneo;
use App\Filament\Resources\Torneos\Pages\CreateTorneo;
use App\Filament\Resources\Torneos\Pages\EditTorneo;
use App\Filament\Resources\Torneos\Pages\HorariosDelTorneo;
use App\Filament\Resources\Torneos\Pages\ListTorneos;
use App\Filament\Resources\Torneos\Pages\PartidosDelTorneo;
use App\Filament\Resources\Torneos\Pages\ViewTorneo;
use App\Filament\Resources\Torneos\RelationManagers\AdministradoresRelationManager;
use App\Filament\Resources\Torneos\RelationManagers\ArbitrosRelationManager;
use App\Filament\Resources\Torneos\RelationManagers\CategoriasRelationManager;
use App\Filament\Resources\Torneos\RelationManagers\ParejasRelationManager;
use App\Filament\Resources\Torneos\RelationManagers\PistasRelationManager;
use App\Filament\Resources\Torneos\Schemas\TorneoForm;
use App\Filament\Resources\Torneos\Schemas\TorneoInfolist;
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

    /** Un administrador de torneo solo ve los suyos; un árbitro, los que arbitra. */
    public static function getEloquentQuery(): Builder
    {
        $consulta = parent::getEloquentQuery();
        $usuario = Filament::auth()->user();

        if ($usuario instanceof User && $usuario->rol === Rol::Administrador) {
            $consulta->whereHas('administradores', fn (Builder $q) => $q->whereKey($usuario->id));
        }
        if ($usuario instanceof User && $usuario->esArbitro()) {
            $consulta->whereIn('torneos.id', $usuario->idsDeTorneosQueArbitra());
        }

        return $consulta;
    }

    /** Si quien está en el panel es un árbitro (o mesa): solo lleva los partidos. */
    public static function esArbitro(): bool
    {
        $usuario = Filament::auth()->user();

        return $usuario instanceof User && $usuario->esArbitro();
    }

    public static function form(Schema $schema): Schema
    {
        return TorneoForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TorneoInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TorneosTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            CategoriasRelationManager::class,
            ParejasRelationManager::class,
            PistasRelationManager::class,
            ArbitrosRelationManager::class,
            AdministradoresRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTorneos::route('/'),
            'create' => CreateTorneo::route('/nuevo'),
            'view' => ViewTorneo::route('/{record}'),
            'edit' => EditTorneo::route('/{record}/editar'),
            'partidos' => PartidosDelTorneo::route('/{record}/partidos'),
            'competicion' => CompeticionDelTorneo::route('/{record}/competicion'),
            'horarios' => HorariosDelTorneo::route('/{record}/horarios'),
        ];
    }
}
