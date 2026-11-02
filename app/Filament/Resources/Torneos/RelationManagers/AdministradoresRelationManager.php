<?php

namespace App\Filament\Resources\Torneos\RelationManagers;

use App\Enums\Rol;
use App\Models\User;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Facades\Filament;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Quién del equipo administra el torneo. Un administrador solo ve y
 * gestiona los torneos que tiene aquí; el propietario los ve todos. Solo
 * el propietario reparte los torneos.
 */
class AdministradoresRelationManager extends RelationManager
{
    protected static string $relationship = 'administradores';

    /** La relación de vuelta en User: para no ofrecer a quien ya está. */
    protected static ?string $inverseRelationship = 'torneosQueAdministra';

    protected static ?string $title = 'Administradores';

    protected static ?string $modelLabel = 'administrador';

    protected static ?string $pluralModelLabel = 'administradores';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $usuario = Filament::auth()->user();

        return $usuario instanceof User && $usuario->esPropietario();
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nombre')
            ->emptyStateHeading('Sin administradores')
            ->emptyStateDescription('Solo usted (propietario) ve este torneo. Añada aquí a quien deba gestionarlo; se dan de alta en Organización › Equipo con el rol de administrador.')
            ->columns([
                TextColumn::make('nombre')->label('Nombre')->formatStateUsing(fn (User $record): string => $record->nombre_completo),
                TextColumn::make('email')->label('Correo electrónico'),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Añadir administrador')
                    ->modalHeading('Añadir un administrador al torneo')
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(fn (Builder $query): Builder => $query->where('rol', Rol::Administrador->value)->where('activo', true))
                    ->recordSelectSearchColumns(['nombre', 'apellidos', 'email']),
            ])
            ->recordActions([
                DetachAction::make()->label('Quitar'),
            ]);
    }
}
