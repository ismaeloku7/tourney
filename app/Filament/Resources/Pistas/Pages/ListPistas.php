<?php

namespace App\Filament\Resources\Pistas\Pages;

use App\Filament\Avisos;
use App\Filament\Resources\Pistas\PistaResource;
use App\Models\Pista;
use App\Models\Sede;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListPistas extends ListRecords
{
    protected static string $resource = PistaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('crearVarias')
                ->label('Crear varias')
                ->icon(Heroicon::OutlinedSquares2x2)
                ->color('gray')
                ->modalHeading('Crear varias pistas')
                ->modalDescription('Se crean numeradas y activas: «Pista 1», «Pista 2»… Después se renombra la que haga falta.')
                ->modalSubmitActionLabel('Crear')
                ->schema([
                    Select::make('sede_id')->label('Sede')->placeholder('Sin sede')
                        ->options(fn (): array => Sede::query()->orderBy('nombre')->pluck('nombre', 'id')->all()),
                    TextInput::make('prefijo')->label('Nombre')->required()->maxLength(50)->default('Pista'),
                    TextInput::make('desde')->label('Desde el número')->required()->integer()->minValue(0)->default(1),
                    TextInput::make('cuantas')->label('Cuántas')->required()->integer()->minValue(1)->maxValue(50)->default(4),
                ])
                ->action(function (array $data): void {
                    $sede = filled($data['sede_id'] ?? null) ? (int) $data['sede_id'] : null;
                    $prefijo = trim((string) $data['prefijo']);
                    $desde = (int) $data['desde'];
                    $cuantas = (int) $data['cuantas'];
                    $orden = (int) Pista::query()->where('sede_id', $sede)->max('orden');

                    for ($n = $desde; $n < $desde + $cuantas; $n++) {
                        Pista::query()->create(['sede_id' => $sede, 'nombre' => "{$prefijo} {$n}", 'orden' => ++$orden, 'activa' => true]);
                    }

                    Avisos::hecho("{$cuantas} pistas creadas", "De «{$prefijo} {$desde}» a «{$prefijo} ".($desde + $cuantas - 1).'».');
                }),
        ];
    }
}
