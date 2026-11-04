<?php

namespace App\Filament\Resources\Parejas;

use App\Actions\Jugadores\CambiarNivel;
use App\Competicion\Errores\ErrorDeCompeticion;
use App\Filament\Avisos;
use App\Models\Inscripcion;
use App\Models\Jugador;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;

/**
 * El botón «Niveles» de una pareja en el panel: un campo por jugador con su
 * nivel (un número del 0 al 10). La organización lo cambia siempre, esté
 * como esté la inscripción.
 */
final class NivelesDePareja
{
    public static function accion(): Action
    {
        return Action::make('niveles')
            ->label('Niveles')
            ->icon(Heroicon::OutlinedChartBar)
            ->color('gray')
            ->modalHeading(fn (Inscripcion $record): string => 'Niveles de '.$record->participante->nombre)
            ->modalDescription('Un número del 0 al 10, como 3 o 2,5. En blanco, sin nivel.')
            ->modalSubmitActionLabel('Guardar niveles')
            ->modalWidth('md')
            ->fillForm(fn (Inscripcion $record): array => $record->participante->jugadores
                ->mapWithKeys(fn (Jugador $j): array => [self::campo($j) => $j->nivel_declarado])
                ->all())
            ->schema(fn (Inscripcion $record): array => $record->participante->jugadores
                ->map(fn (Jugador $j): TextInput => TextInput::make(self::campo($j))
                    ->label($j->nombre_completo)
                    ->placeholder('3 o 2,5')
                    ->maxLength(5)
                    ->regex('/^\d{1,2}([.,]\d{1,2})?$/')
                    ->validationMessages(['regex' => 'Un número del 0 al 10, como 3 o 2,5.']))
                ->values()
                ->all())
            ->action(function (Inscripcion $record, array $data, Action $action): void {
                $usuario = Filament::auth()->user();

                try {
                    foreach ($record->participante->jugadores as $jugador) {
                        app(CambiarNivel::class)->porLaOrganizacion($jugador, isset($data[self::campo($jugador)]) ? (string) $data[self::campo($jugador)] : null, $usuario instanceof User ? $usuario : null);
                    }
                } catch (ErrorDeCompeticion $e) {
                    Avisos::error('No se han podido guardar', [$e->getMessage()]);
                    $action->halt();

                    return;
                }

                Avisos::hecho('Niveles guardados', $record->participante->refresh()->nivelMedioTexto());
            });
    }

    private static function campo(Jugador $jugador): string
    {
        return 'nivel_'.$jugador->id;
    }
}
