<?php

namespace App\Filament\Resources\Parejas;

use App\Actions\Inscripciones\CompletarPareja;
use App\Competicion\Errores\ErrorDeCompeticion;
use App\Enums\Genero;
use App\Filament\Avisos;
use App\Models\Inscripcion;
use App\Models\Jugador;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * El botón «Añadir compañero» de una pareja incompleta: da de alta al
 * jugador que falta con todos sus datos (o lo elige si ya está en la base)
 * y completa la pareja, confirmándola si se pide.
 */
final class CompaneroDePareja
{
    public static function accion(): Action
    {
        $nuevo = fn (Get $get): bool => $get('origen') !== 'existente';

        return Action::make('companero')
            ->label('Añadir compañero')
            ->icon(Heroicon::OutlinedUserPlus)
            ->color('warning')
            ->visible(fn (Inscripcion $record): bool => $record->participante->jugadores->count() < 2)
            ->modalHeading(fn (Inscripcion $record): string => 'Compañero de '.($record->participante->jugadores->first()->nombre_completo ?? $record->participante->nombre))
            ->modalDescription(fn (Inscripcion $record): string => $record->participante->nombre.' · '.$record->categoria->nombre)
            ->modalSubmitActionLabel('Añadir a la pareja')
            ->schema([
                Radio::make('origen')->label('¿Quién es?')->inline()->live()->default('nuevo')
                    ->options(['nuevo' => 'Un jugador nuevo', 'existente' => 'Ya está en la base de jugadores']),
                Select::make('jugador_id')->label('Jugador')->searchable()
                    ->options(fn (): array => Jugador::query()->whereNull('anonimizado_en')->orderBy('apellidos')->orderBy('nombre')->get()
                        ->mapWithKeys(fn (Jugador $j): array => [$j->id => $j->nombre_completo.($j->getAttribute('email') ? ' · '.$j->getAttribute('email') : '')])->all())
                    ->required(fn (Get $get): bool => ! $nuevo($get))
                    ->visible(fn (Get $get): bool => ! $nuevo($get)),
                Grid::make(2)
                    ->visible($nuevo)
                    ->schema([
                        TextInput::make('nombre')->label('Nombre')->required($nuevo)->maxLength(80),
                        TextInput::make('apellidos')->label('Apellidos')->required($nuevo)->maxLength(120),
                        TextInput::make('email')->label('Correo electrónico')->email()->maxLength(190)->autocomplete('off')
                            // La regla de Laravel, no ->unique(): en la acción de una pareja, Filament excluiría su id de la tabla de jugadores.
                            ->rule(Rule::unique(Jugador::class, 'email'))
                            ->validationMessages(['unique' => 'Ya hay un jugador con este correo: elíjalo en «Ya está en la base de jugadores».'])
                            ->helperText('Con su correo entra en su portal y le llegan los avisos.'),
                        TextInput::make('telefono')->label('Teléfono')->type('tel')->maxLength(30),
                        TextInput::make('nivel_declarado')->label('Nivel')->placeholder('3 o 2,5')->maxLength(5)
                            ->regex('/^(10([.,]0{1,2})?|\d([.,]\d{1,2})?)$/')
                            ->validationMessages(['regex' => 'Un número del 0 al 10, como 3 o 2,5.']),
                        Select::make('genero')->label('Género')->options(Genero::opciones())->placeholder('Sin indicar'),
                        DatePicker::make('fecha_nacimiento')->label('Fecha de nacimiento')->native(false)->displayFormat('d/m/Y')->maxDate(now()),
                        TextInput::make('alias')->label('Alias')->maxLength(60)->helperText('Si el torneo enseña alias en público.'),
                    ]),
                Toggle::make('confirmar')->label('Confirmar la inscripción')->default(true)
                    ->helperText('Si la categoría está al cupo, va a la lista de espera.'),
                Toggle::make('avisar')->label('Avisar a los jugadores por correo')->default(false),
            ])
            ->action(function (Inscripcion $record, array $data, Action $action): void {
                $usuario = Filament::auth()->user();

                try {
                    DB::transaction(function () use ($record, $data, $usuario): void {
                        $companero = ($data['origen'] ?? 'nuevo') === 'existente'
                            ? Jugador::query()->findOrFail((int) $data['jugador_id'])
                            : Jugador::query()->create(self::datosDelJugador($data));

                        app(CompletarPareja::class)->handle($record, $companero, (bool) ($data['confirmar'] ?? true), $usuario instanceof User ? $usuario : null, (bool) ($data['avisar'] ?? false));
                    });
                } catch (ErrorDeCompeticion $e) {
                    Avisos::error('No se ha podido completar la pareja', [$e->getMessage()]);
                    $action->halt();

                    return;
                }

                Avisos::hecho('Pareja completa', $record->refresh()->participante->nombre.' · '.$record->estado->etiqueta());
            });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function datosDelJugador(array $data): array
    {
        $texto = fn (string $campo): ?string => is_string($data[$campo] ?? null) && trim($data[$campo]) !== '' ? trim($data[$campo]) : null;

        return [
            'nombre' => (string) $texto('nombre'),
            'apellidos' => (string) $texto('apellidos'),
            'email' => $texto('email') !== null ? mb_strtolower((string) $texto('email')) : null,
            'telefono' => $texto('telefono'),
            'nivel_declarado' => $texto('nivel_declarado'),
            'genero' => $texto('genero'),
            'fecha_nacimiento' => $texto('fecha_nacimiento'),
            'alias' => $texto('alias'),
        ];
    }
}
