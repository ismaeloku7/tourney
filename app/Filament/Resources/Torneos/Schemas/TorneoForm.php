<?php

namespace App\Filament\Resources\Torneos\Schemas;

use App\Enums\ResultadosPorJugadores;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

/**
 * Los datos del torneo: lo que ve el público, las fechas y las reglas que
 * valen para todas sus categorías.
 */
class TorneoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Datos')
                    ->columns(2)
                    ->schema([
                        TextInput::make('nombre')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(160)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Set $set, ?string $state, string $operation): mixed => $operation === 'create' ? $set('slug', Str::slug((string) $state)) : null),
                        TextInput::make('slug')
                            ->label('Dirección')
                            ->required()
                            ->maxLength(80)
                            ->alphaDash()
                            ->unique(ignoreRecord: true)
                            ->prefix('/torneos/')
                            ->helperText('Sin espacios ni acentos. Sale en el enlace público del torneo.'),
                        Select::make('sede_id')
                            ->label('Sede')
                            ->relationship('sede', 'nombre')
                            ->placeholder('Sin sede'),
                        TextInput::make('direccion')
                            ->label('Dirección postal')
                            ->maxLength(255)
                            ->helperText('Si no es la de la sede.'),
                        DatePicker::make('fecha_inicio')
                            ->label('Empieza')
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                        DatePicker::make('fecha_fin')
                            ->label('Acaba')
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->afterOrEqual('fecha_inicio'),
                        DateTimePicker::make('inscripcion_desde')
                            ->label('Inscripción abierta desde')
                            ->native(false)
                            ->displayFormat('d/m/Y H:i')
                            ->seconds(false),
                        DateTimePicker::make('inscripcion_hasta')
                            ->label('Inscripción abierta hasta')
                            ->native(false)
                            ->displayFormat('d/m/Y H:i')
                            ->seconds(false)
                            ->afterOrEqual('inscripcion_desde'),
                        TextInput::make('contacto')
                            ->label('Contacto para los jugadores')
                            ->maxLength(255)
                            ->helperText('Un teléfono o un correo que sale en la página del torneo.'),
                        Textarea::make('normativa')
                            ->label('Normativa')
                            ->rows(6)
                            ->columnSpanFull(),
                    ]),
                Section::make('Reglas')
                    ->columns(2)
                    ->schema([
                        Select::make('resultados_por_jugadores')
                            ->label('Resultados por jugadores')
                            ->options(ResultadosPorJugadores::opciones())
                            ->default(ResultadosPorJugadores::Desactivado->value)
                            ->required()
                            ->live(),
                        TextInput::make('minutos_para_validar')
                            ->label('Minutos para validar')
                            ->integer()
                            ->minValue(5)
                            ->maxValue(1440)
                            ->default(30)
                            ->helperText('Si el rival no valida en ese tiempo, el resultado se da por bueno.'),
                        Toggle::make('privacidad_alias')
                            ->label('Enseñar alias en público en vez del nombre'),
                        Toggle::make('listado_en_plataforma')
                            ->label('Listar en el buscador de la plataforma'),
                        TextInput::make('descanso_minimo_minutos')
                            ->label('Descanso mínimo entre partidos (minutos)')
                            ->integer()
                            ->minValue(0)
                            ->maxValue(600)
                            ->default(30),
                        Select::make('margen_entre_partidos_minutos')
                            ->label('Margen entre partidos')
                            ->options([0 => 'Sin margen', 5 => '5 minutos', 10 => '10 minutos'])
                            ->default(0)
                            ->required(),
                    ]),
            ]);
    }
}
