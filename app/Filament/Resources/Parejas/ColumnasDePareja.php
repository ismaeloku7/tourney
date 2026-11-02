<?php

namespace App\Filament\Resources\Parejas;

use App\Enums\EstadoInscripcion;
use App\Models\Inscripcion;
use App\Models\Jugador;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;

/**
 * Las columnas de una pareja (una inscripción con su participante y sus
 * jugadores), las mismas en el apartado Parejas y en la pestaña Parejas
 * del torneo. Esperan cargados `participante.jugadores`.
 */
final class ColumnasDePareja
{
    public static function nombre(): TextColumn
    {
        return TextColumn::make('participante.nombre')->label('Pareja')->searchable()->weight('bold');
    }

    public static function jugadores(): TextColumn
    {
        return TextColumn::make('jugadores')->label('Jugadores')
            ->state(fn (Inscripcion $record): array => $record->participante->jugadores->map(fn (Jugador $j): string => $j->nombre_completo)->all())
            ->listWithLineBreaks();
    }

    public static function niveles(): TextColumn
    {
        return TextColumn::make('niveles')->label('Nivel')
            ->state(fn (Inscripcion $record): array => $record->participante->jugadores->map(fn (Jugador $j): string => $j->nivel_declarado ?? '—')->all())
            ->listWithLineBreaks()
            ->description(fn (Inscripcion $record): ?string => $record->participante->nivelMedioTexto());
    }

    public static function estado(): TextColumn
    {
        return TextColumn::make('estado')->label('Estado')->badge()
            ->formatStateUsing(fn (EstadoInscripcion $state, Inscripcion $record): string => $state->etiqueta()
                .($record->participante->jugadores->count() < 2 ? ' · falta compañero' : '')
                .($record->posicion_espera !== null ? " (#{$record->posicion_espera})" : ''))
            ->color(fn (EstadoInscripcion $state): string => $state->color());
    }

    public static function pago(): IconColumn
    {
        return IconColumn::make('pago_marcado_en')->label('Pago')->boolean()->trueIcon(Heroicon::OutlinedBanknotes)->falseIcon(Heroicon::OutlinedMinus)
            ->state(fn (Inscripcion $record): bool => $record->pago_marcado_en !== null);
    }
}
