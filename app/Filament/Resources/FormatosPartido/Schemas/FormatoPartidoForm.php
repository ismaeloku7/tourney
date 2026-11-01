<?php

namespace App\Filament\Resources\FormatosPartido\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * Las reglas de un formato, con los valores del briefing. La coherencia
 * entre ellas (tie-break a los juegos del set, super tie-break solo con
 * más de un set) la comprueba el DTO al guardar.
 */
class FormatoPartidoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TextInput::make('nombre')->label('Nombre')->required()->maxLength(120)->helperText('Ej.: «Al mejor de 3 sets a 6», «Americano a 9».'),
                Section::make('Sets y juegos')
                    ->columns(3)
                    ->schema([
                        Select::make('sets')->label('Sets')->options([1 => 'Un set', 3 => 'Al mejor de 3', 5 => 'Al mejor de 5'])->default(3)->required()->live(),
                        Select::make('juegos_por_set')->label('Juegos por set')->options([4 => '4', 6 => '6', 9 => '9 (set largo)'])->default(6)->required()->live(),
                        Select::make('diferencia_para_ganar_set')->label('Diferencia para ganar el set')->options([1 => '1 juego', 2 => '2 juegos'])->default(2)->required(),
                        Select::make('tie_break_en')->label('Tie-break')
                            ->options(fn (Get $get): array => match ((int) $get('juegos_por_set')) {
                                4 => [4 => 'A 4 iguales'],
                                9 => [8 => 'A 8 iguales'],
                                default => [6 => 'A 6 iguales'],
                            })
                            ->default(6)
                            ->placeholder('Sin tie-break')
                            ->helperText('Sin tie-break, el set sigue hasta sacar la diferencia.'),
                        TextInput::make('tie_break_puntos')->label('Puntos del tie-break')->integer()->minValue(5)->maxValue(21)->default(7),
                        TextInput::make('duracion_estimada')->label('Duración estimada (minutos)')->integer()->minValue(5)->maxValue(600)->default(60)->required()->helperText('Para el planificador.'),
                    ]),
                Section::make('Variantes')
                    ->columns(3)
                    ->schema([
                        Toggle::make('super_tie_break_final')->label('Super tie-break en el set decisivo')->visible(fn (Get $get): bool => (int) $get('sets') > 1),
                        TextInput::make('super_tie_break_puntos')->label('Puntos del super tie-break')->integer()->minValue(5)->maxValue(21)->default(10),
                        Toggle::make('punto_de_oro')->label('Punto de oro')->default(true)->helperText('Informativo: no afecta al cálculo.'),
                        TextInput::make('por_tiempo_minutos')->label('Partido por tiempo (minutos)')->integer()->minValue(5)->maxValue(240)->helperText('Al acabar el tiempo gana quien va delante en juegos. En blanco, sin límite de tiempo.'),
                        Toggle::make('es_predeterminado')->label('Formato predeterminado')->helperText('El que usan las categorías que no eligen otro.'),
                    ]),
            ]);
    }
}
