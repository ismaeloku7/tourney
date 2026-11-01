<?php

namespace App\Filament\Resources\Categorias\Schemas;

use App\Enums\Genero;
use App\Enums\Modalidad;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

/** Los datos de una categoría: los mismos en la ficha del torneo y en la suya. */
class CategoriaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components(self::campos());
    }

    /** @return list<\Filament\Schemas\Components\Component> */
    public static function campos(): array
    {
        return [
            TextInput::make('nombre')->label('Nombre')->required()->maxLength(80),
            Select::make('genero')->label('Género')->options(Genero::opciones())->default(Genero::Abierto->value)->required(),
            Select::make('modalidad')->label('Modalidad')->options(Modalidad::opciones())->default(Modalidad::Parejas->value)->required()
                ->helperText('La v1 juega por parejas.'),
            TextInput::make('nivel')->label('Nivel')->maxLength(40)->helperText('3ª, 4ª, B… solo informativo.'),
            TextInput::make('cupo')->label('Cupo de parejas')->integer()->minValue(2)->maxValue(500)->helperText('En blanco, sin límite. Al llenarse, las nuevas parejas van a la lista de espera.'),
            TextInput::make('minimo_participantes')->label('Mínimo para celebrarse')->integer()->minValue(0)->default(0),
            TextInput::make('precio')->label('Precio por pareja')->numeric()->minValue(0)->suffix('€')->helperText('Informativo.'),
            Select::make('formato_partido_id')->label('Formato de partido')->relationship('formatoPartido', 'nombre')->placeholder('El predeterminado del organizador'),
            TextInput::make('orden')->label('Orden')->integer()->minValue(0)->default(1)->helperText('También es la prioridad al planificar horarios.'),
        ];
    }
}
