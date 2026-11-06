<?php

namespace App\Filament\Resources\Torneos\Schemas;

use App\Enums\EstadoTorneo;
use App\Enums\ResultadosPorJugadores;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/** La ficha del torneo, para leer: lo mismo que el formulario, sin poder tocarlo. */
class TorneoInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Datos')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('nombre')->label('Nombre'),
                        TextEntry::make('estado')->label('Estado')->badge()
                            ->formatStateUsing(fn (EstadoTorneo $state): string => $state->etiqueta())
                            ->color(fn (EstadoTorneo $state): string => $state->color()),
                        TextEntry::make('slug')->label('Dirección pública')->prefix('/torneos/'),
                        TextEntry::make('fecha_inicio')->label('Empieza')->date('d/m/Y'),
                        TextEntry::make('fecha_fin')->label('Acaba')->date('d/m/Y'),
                        TextEntry::make('sede.nombre')->label('Sede')->placeholder('Sin sede'),
                        TextEntry::make('direccion')->label('Dirección postal')->placeholder('La de la sede'),
                        TextEntry::make('contacto')->label('Contacto para los jugadores')->placeholder('—'),
                        IconEntry::make('inscripcion_online')->label('Inscripción online')->boolean(),
                        TextEntry::make('inscripcion_desde')->label('Inscripción desde')->dateTime('d/m/Y H:i')->placeholder('—'),
                        TextEntry::make('inscripcion_hasta')->label('Inscripción hasta')->dateTime('d/m/Y H:i')->placeholder('—'),
                        TextEntry::make('normativa')->label('Normativa')->placeholder('Sin normativa')->columnSpanFull(),
                    ]),
                Section::make('Reglas')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('resultados_por_jugadores')->label('Resultados por jugadores')
                            ->formatStateUsing(fn (mixed $state): string => $state instanceof ResultadosPorJugadores ? $state->etiqueta() : (string) $state),
                        TextEntry::make('minutos_para_validar')->label('Minutos para validar')->suffix(' min'),
                        TextEntry::make('margen_entre_partidos_minutos')->label('Calentamiento en pista')
                            ->formatStateUsing(fn (mixed $state): string => (int) $state === 0 ? 'Sin calentamiento' : (int) $state.' minutos'),
                        TextEntry::make('descanso_minimo_minutos')->label('Descanso mínimo entre partidos')
                            ->formatStateUsing(fn (mixed $state): string => (int) $state === 0 ? 'Sin descanso (enlazan)' : (int) $state.' minutos'),
                        IconEntry::make('privacidad_alias')->label('Alias en público')->boolean(),
                        IconEntry::make('listado_en_plataforma')->label('En el buscador de la plataforma')->boolean(),
                        IconEntry::make('web_inscritos')->label('Inscritos en la web')->boolean(),
                        IconEntry::make('web_horarios')->label('Horarios en la web')->boolean(),
                    ]),
            ]);
    }
}
