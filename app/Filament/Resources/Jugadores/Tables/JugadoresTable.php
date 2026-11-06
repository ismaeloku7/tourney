<?php

namespace App\Filament\Resources\Jugadores\Tables;

use App\Enums\Genero;
use App\Models\Jugador;
use App\Models\SuscripcionPush;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class JugadoresTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('suscripcionesPush:id,jugador_id,dispositivo'))
            ->defaultSort('apellidos')
            ->columns([
                TextColumn::make('apellidos')->label('Jugador')->formatStateUsing(fn (Jugador $record): string => $record->nombre_completo)->searchable(['nombre', 'apellidos'])->sortable(),
                TextColumn::make('email')->label('Correo')->searchable()->placeholder('—'),
                TextColumn::make('telefono')->label('Teléfono')->placeholder('—'),
                TextColumn::make('genero')->label('Género')->badge()->formatStateUsing(fn (Genero $state): string => $state->etiqueta())->placeholder('—'),
                TextColumn::make('nivel_declarado')->label('Nivel')->placeholder('—')->sortable(),
                TextColumn::make('participantes_count')->label('Torneos')->counts('participantes'),
                TextColumn::make('notificaciones')->label('Notificaciones en el móvil')
                    ->state(fn (Jugador $record): ?string => $record->notificacionesEnElMovil())
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state === 'activadas' ? 'Activadas' : 'Apagadas por el jugador')
                    ->color(fn (?string $state): string => $state === 'activadas' ? 'success' : 'warning')
                    ->description(fn (Jugador $record): ?string => $record->suscripcionesPush->isEmpty() ? null : self::dispositivos($record))
                    ->placeholder('No'),
            ])
            ->filters([
                SelectFilter::make('genero')->label('Género')->options(Genero::opciones()),
                SelectFilter::make('notificaciones')->label('Notificaciones en el móvil')
                    ->options(['activadas' => 'Activadas', 'apagadas' => 'Apagadas por el jugador', 'no' => 'Sin activar'])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'activadas' => Jugador::conNotificacionesActivadas($query),
                        'apagadas' => $query->has('suscripcionesPush')->where('avisos->push', false),
                        'no' => $query->doesntHave('suscripcionesPush'),
                        default => $query,
                    }),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    /** «iPhone, Android» o «2 dispositivos». */
    private static function dispositivos(Jugador $jugador): string
    {
        $nombres = $jugador->suscripcionesPush->map(fn (SuscripcionPush $s): string => $s->dispositivo ?? 'Otro')->unique()->values();

        return $nombres->count() === $jugador->suscripcionesPush->count()
            ? $nombres->implode(', ')
            : $jugador->suscripcionesPush->count().' dispositivos: '.$nombres->implode(', ');
    }
}
