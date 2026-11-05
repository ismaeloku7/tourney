<?php

namespace App\Filament\Resources\Jugadores\RelationManagers;

use App\Enums\EstadoInscripcion;
use App\Filament\Resources\Categorias\CategoriaResource;
use App\Filament\Resources\Parejas\ParejaResource;
use App\Filament\Resources\Torneos\TorneoResource;
use App\Models\Categoria;
use App\Models\Jugador;
use App\Models\Participante;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Los torneos en los que está el jugador, una fila por pareja: el torneo,
 * la categoría, con quién juega y cómo va la inscripción, con saltos al
 * torneo, a la categoría y a la pareja.
 */
class TorneosRelationManager extends RelationManager
{
    protected static string $relationship = 'participantes';

    protected static ?string $title = 'Torneos';

    protected static ?string $modelLabel = 'inscripción';

    protected static ?string $pluralModelLabel = 'inscripciones';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        assert($ownerRecord instanceof Jugador);
        $total = $ownerRecord->participantes()->whereHas('inscripcion', fn (Builder $q) => $q->where('estado', '!=', EstadoInscripcion::Baja->value))->count();

        return $total > 0 ? (string) $total : null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['categoria.torneo', 'jugadores', 'inscripcion'])
                ->orderByDesc(Categoria::query()->select('torneo_id')->whereColumn('categorias.id', 'participantes.categoria_id'))
                ->orderBy('participantes.id'))
            ->emptyStateHeading('No está en ningún torneo')
            ->emptyStateDescription('Se apunta desde la categoría del torneo, o se inscribe él mismo desde la web.')
            ->columns([
                TextColumn::make('categoria.torneo.nombre')->label('Torneo')->weight('bold')
                    ->description(fn (Participante $record): string => $record->categoria->torneo->fecha_inicio->translatedFormat('j M Y').' · '.$record->categoria->torneo->estado->etiqueta()),
                TextColumn::make('categoria.nombre')->label('Categoría')->badge()->color('gray'),
                TextColumn::make('nombre')->label('Pareja'),
                TextColumn::make('companero')->label('Con')->placeholder('Sin compañero')
                    ->state(function (Participante $record): ?string {
                        /** @var Jugador $yo */
                        $yo = $this->getOwnerRecord();

                        return $record->jugadores->first(fn (Jugador $j): bool => ! $j->is($yo))?->nombre_completo;
                    }),
                TextColumn::make('inscripcion.estado')->label('Estado')->badge()->placeholder('—')
                    ->formatStateUsing(fn (EstadoInscripcion $state, Participante $record): string => $state->etiqueta().($record->inscripcion?->posicion_espera !== null ? " (#{$record->inscripcion->posicion_espera})" : ''))
                    ->color(fn (EstadoInscripcion $state): string => $state->color()),
                IconColumn::make('pago')->label('Pago')->boolean()->trueIcon(Heroicon::OutlinedBanknotes)->falseIcon(Heroicon::OutlinedMinus)
                    ->state(fn (Participante $record): bool => $record->inscripcion?->pago_marcado_en !== null),
            ])
            ->recordUrl(fn (Participante $record): ?string => $record->inscripcion === null ? null : ParejaResource::getUrl('edit', ['record' => $record->inscripcion]))
            ->recordActions([
                Action::make('torneo')->label('Torneo')->icon(Heroicon::OutlinedTrophy)->color('gray')
                    ->url(fn (Participante $record): string => TorneoResource::getUrl('edit', ['record' => $record->categoria->torneo_id])),
                Action::make('categoria')->label('Categoría')->icon(Heroicon::OutlinedRectangleStack)->color('gray')
                    ->url(fn (Participante $record): string => CategoriaResource::getUrl('edit', ['record' => $record->categoria_id])),
                Action::make('pareja')->label('Pareja')->icon(Heroicon::OutlinedUsers)->color('gray')
                    ->visible(fn (Participante $record): bool => $record->inscripcion !== null)
                    ->url(fn (Participante $record): string => ParejaResource::getUrl('edit', ['record' => $record->inscripcion])),
            ]);
    }
}
