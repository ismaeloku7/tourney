<?php

namespace App\Filament\Resources\Parejas;

use App\Enums\EstadoInscripcion;
use App\Models\Inscripcion;
use App\Models\Jugador;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;

/**
 * Las columnas de una pareja (una inscripción con su participante y sus
 * jugadores), las mismas en el apartado Parejas y en la pestaña Parejas
 * del torneo. Esperan cargados `participante.jugadores`. Todas se pueden
 * ocultar (botón de columnas), para copiar solo lo que interesa.
 */
final class ColumnasDePareja
{
    public static function nombre(): TextColumn
    {
        return TextColumn::make('participante.nombre')->label('Pareja')->searchable()->weight('bold')->toggleable();
    }

    /** Cada jugador enlaza a su ficha. */
    public static function jugadores(): ViewColumn
    {
        return ViewColumn::make('jugadores')->label('Jugadores')->view('filament.tablas.jugadores')->toggleable();
    }

    public static function niveles(): TextColumn
    {
        return TextColumn::make('niveles')->label('Nivel')
            ->state(fn (Inscripcion $record): array => $record->participante->jugadores->map(fn (Jugador $j): string => $j->nivel_declarado ?? '—')->all())
            ->listWithLineBreaks()
            ->description(fn (Inscripcion $record): ?string => $record->participante->nivelMedioTexto())
            ->toggleable();
    }

    public static function estado(): TextColumn
    {
        return TextColumn::make('estado')->label('Estado')->badge()
            ->formatStateUsing(fn (EstadoInscripcion $state, Inscripcion $record): string => $state->etiqueta()
                .($record->participante->jugadores->count() < 2 ? ' · falta compañero' : '')
                .($record->posicion_espera !== null ? " (#{$record->posicion_espera})" : ''))
            ->color(fn (EstadoInscripcion $state): string => $state->color())
            ->toggleable();
    }

    public static function pago(): IconColumn
    {
        return IconColumn::make('pago_marcado_en')->label('Pago')->boolean()->trueIcon(Heroicon::OutlinedBanknotes)->falseIcon(Heroicon::OutlinedMinus)
            ->state(fn (Inscripcion $record): bool => $record->pago_marcado_en !== null)
            ->toggleable();
    }
}
