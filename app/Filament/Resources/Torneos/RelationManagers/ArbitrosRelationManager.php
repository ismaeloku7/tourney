<?php

namespace App\Filament\Resources\Torneos\RelationManagers;

use App\Enums\Rol;
use App\Models\Pista;
use App\Models\Torneo;
use App\Models\TorneoArbitro;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Los árbitros del torneo y las pistas que lleva cada uno en la mesa
 * (/mesa). Sin pista, todas las del torneo.
 */
class ArbitrosRelationManager extends RelationManager
{
    protected static string $relationship = 'arbitros';

    protected static ?string $title = 'Árbitros';

    protected static ?string $modelLabel = 'árbitro';

    protected static ?string $pluralModelLabel = 'árbitros';

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('user_id')->label('Árbitro')->required()->searchable()
                ->options(fn (): array => User::query()->where('rol', Rol::Arbitro->value)->where('activo', true)->orderBy('nombre')->get()
                    ->mapWithKeys(fn (User $u): array => [$u->id => $u->nombre_completo])->all())
                ->helperText('Los árbitros se dan de alta en Equipo, con el rol de árbitro.'),
            Select::make('pista_id')->label('Pista')
                ->options(fn (): array => Pista::query()->whereIn('id', $this->torneo()->torneoPistas()->pluck('pista_id'))->orderBy('orden')->pluck('nombre', 'id')->all())
                ->placeholder('Todas las del torneo'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading('Sin árbitros')
            ->emptyStateDescription('Los árbitros anotan los resultados de sus pistas desde /mesa en el móvil.')
            ->columns([
                TextColumn::make('usuario.nombre')->label('Árbitro')->formatStateUsing(fn (TorneoArbitro $record): string => $record->usuario->nombre_completo),
                TextColumn::make('pista.nombre')->label('Pista')->placeholder('Todas'),
            ])
            ->headerActions([
                CreateAction::make()->label('Añadir árbitro'),
            ])
            ->recordActions([
                DeleteAction::make()->label('Quitar'),
            ]);
    }

    private function torneo(): Torneo
    {
        $torneo = $this->getOwnerRecord();
        assert($torneo instanceof Torneo);

        return $torneo;
    }
}
