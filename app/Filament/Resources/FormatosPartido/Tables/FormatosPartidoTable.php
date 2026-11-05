<?php

namespace App\Filament\Resources\FormatosPartido\Tables;

use App\Actions\Competicion\DuracionesAprendidas;
use App\Competicion\EstimadorDeDuracion;
use App\Models\FormatoPartido;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FormatosPartidoTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('nombre')
            ->columns([
                TextColumn::make('nombre')->label('Formato')->searchable(),
                TextColumn::make('resumen')->label('Reglas')->state(fn (FormatoPartido $record): string => self::resumen($record)),
                TextColumn::make('duracion_estimada')->label('Duración estimada')->suffix(' min'),
                TextColumn::make('duracion_real')->label('Duración real')
                    ->state(function (FormatoPartido $record): string {
                        $resumen = app(DuracionesAprendidas::class)->estimador()->resumen($record->id);

                        return $resumen === null
                            ? 'Aún sin datos'
                            : $resumen['minutos'].' min (mediana de '.$resumen['partidos'].' partidos)';
                    })
                    ->tooltip('Lo que duran de verdad, sin el calentamiento, medido en los partidos jugados en directo. Con '.EstimadorDeDuracion::MINIMO_DE_PARTIDOS.' o más, el planificador usa esta en vez de la estimada.'),
                IconColumn::make('es_predeterminado')->label('Predeterminado')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function resumen(FormatoPartido $formato): string
    {
        if ($formato->por_tiempo_minutos !== null) {
            return "Por tiempo, {$formato->por_tiempo_minutos} minutos";
        }

        $sets = $formato->sets === 1 ? 'Un set' : "Al mejor de {$formato->sets}";
        $tieBreak = $formato->tie_break_en === null ? 'sin tie-break' : "tie-break a {$formato->tie_break_en} iguales";
        $super = $formato->super_tie_break_final ? ", super tie-break a {$formato->super_tie_break_puntos}" : '';

        return "{$sets} a {$formato->juegos_por_set}, {$tieBreak}{$super}";
    }
}
