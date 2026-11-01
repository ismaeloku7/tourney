<?php

namespace App\Filament\Competicion;

use App\Actions\Competicion\AnularResultado;
use App\Actions\Competicion\ProgramarPartido;
use App\Actions\Competicion\RegistrarResultado;
use App\Actions\Competicion\ValidarResultado;
use App\Competicion\Datos\Resultado;
use App\Competicion\Datos\Set as SetDto;
use App\Competicion\Errores\ErrorDeCompeticion;
use App\Competicion\Errores\ResultadoInvalido;
use App\Enums\EstadoPartido;
use App\Enums\ResultadoEspecial;
use App\Filament\Avisos;
use App\Models\Franja;
use App\Models\Partido;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lo que la organización hace con un partido desde cualquier tabla del
 * panel: registrar el resultado, validar el que dejó un jugador, anularlo
 * y ponerle hora.
 */
final class AccionesDePartido
{
    public static function resultado(): Action
    {
        return Action::make('resultado')
            ->label('Resultado')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('primary')
            ->visible(fn (Partido $record): bool => in_array($record->estado, [EstadoPartido::Pendiente, EstadoPartido::Programado, EstadoPartido::EnJuego], true) && $record->local_id !== null && $record->visitante_id !== null)
            ->modalHeading(fn (Partido $record): string => Marcador::lado($record, true).' – '.Marcador::lado($record, false))
            ->modalDescription(fn (Partido $record): string => 'Formato: '.$record->formatoEfectivo()->nombre.'. El primer marcador de cada set es el de '.Marcador::lado($record, true).'.')
            ->modalSubmitActionLabel('Guardar resultado')
            ->fillForm(fn (Partido $record): array => ['sets' => array_fill(0, max(2, $record->formatoEfectivo()->sets - 1), ['juegos_local' => null, 'juegos_visitante' => null])])
            ->schema([
                Select::make('especial')->label('Partido no jugado o no acabado')
                    ->options(ResultadoEspecial::opciones())->placeholder('Se jugó: anoto los sets')->live(),
                Radio::make('gana_local')->label('Gana')
                    ->options(fn (Partido $record): array => ['1' => Marcador::lado($record, true), '0' => Marcador::lado($record, false)])
                    ->visible(fn (Get $get, Partido $record): bool => (filled($get('especial')) && ResultadoEspecial::from((string) $get('especial'))->tieneGanador()) || $record->formatoEfectivo()->por_tiempo_minutos !== null)
                    ->required(fn (Get $get): bool => filled($get('especial')) && ResultadoEspecial::from((string) $get('especial'))->tieneGanador()),
                Repeater::make('sets')->label('Sets')
                    ->visible(fn (Get $get): bool => blank($get('especial')))
                    ->addActionLabel('Añadir set')
                    ->reorderable(false)
                    ->columns(5)
                    ->schema([
                        TextInput::make('juegos_local')->label('Local')->integer()->minValue(0)->maxValue(99),
                        TextInput::make('juegos_visitante')->label('Visitante')->integer()->minValue(0)->maxValue(99),
                        TextInput::make('tie_break_local')->label('TB local')->integer()->minValue(0)->maxValue(99),
                        TextInput::make('tie_break_visitante')->label('TB visitante')->integer()->minValue(0)->maxValue(99),
                        Toggle::make('es_super_tie_break')->label('Súper TB')->inline(false),
                    ])
                    ->helperText('Los sets que no se jugaron se dejan en blanco. En un 7-6, los puntos del tie-break van en «TB». Un súper tie-break se anota con sus puntos y la casilla marcada.'),
            ])
            ->action(function (Partido $record, array $data, Action $action): void {
                try {
                    app(RegistrarResultado::class)->handle($record, self::resultadoDesde($data), self::usuario());
                } catch (ResultadoInvalido $e) {
                    Avisos::error('El resultado no cuadra con el formato', $e->errores);
                    $action->halt();

                    return;
                } catch (ErrorDeCompeticion $e) {
                    Avisos::error('No se ha podido registrar', [$e->getMessage()]);
                    $action->halt();

                    return;
                }

                Avisos::hecho('Resultado registrado', Marcador::resultado($record->refresh()));
            });
    }

    public static function validar(): Action
    {
        return Action::make('validar')
            ->label('Validar resultado')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->visible(fn (Partido $record): bool => $record->estado === EstadoPartido::PendienteValidacion)
            ->requiresConfirmation()
            ->modalDescription(fn (Partido $record): string => 'Lo registró un jugador: '.Marcador::resultado($record).'. Al validarlo cuenta en la clasificación o en el cuadro.')
            ->action(function (Partido $record, Action $action): void {
                try {
                    app(ValidarResultado::class)->handle($record, self::usuario());
                } catch (ErrorDeCompeticion $e) {
                    Avisos::error('No se ha podido validar', [$e->getMessage()]);
                    $action->halt();

                    return;
                }

                Avisos::hecho('Resultado validado');
            });
    }

    public static function anular(): Action
    {
        return Action::make('anular')
            ->label('Anular resultado')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('danger')
            ->visible(fn (Partido $record): bool => $record->estado->cerrado() || $record->estado === EstadoPartido::PendienteValidacion)
            ->requiresConfirmation()
            ->modalDescription('El partido vuelve a quedar sin resultado. En un cuadro, se vacían los huecos a los que ya había pasado alguien; si el siguiente partido ya se jugó, no se puede.')
            ->action(function (Partido $record, Action $action): void {
                try {
                    app(AnularResultado::class)->handle($record, self::usuario());
                } catch (ErrorDeCompeticion $e) {
                    Avisos::error('No se ha podido anular', [$e->getMessage()]);
                    $action->halt();

                    return;
                }

                Avisos::hecho('Resultado anulado');
            });
    }

    public static function programar(): Action
    {
        return Action::make('programar')
            ->label('Hora y pista')
            ->icon(Heroicon::OutlinedClock)
            ->color('gray')
            ->visible(fn (Partido $record): bool => in_array($record->estado, [EstadoPartido::Pendiente, EstadoPartido::Programado], true))
            ->fillForm(fn (Partido $record): array => ['franja_id' => $record->franja_id])
            ->schema([
                Select::make('franja_id')->label('Franja')->searchable()->preload()
                    ->options(fn (Partido $record): array => Franja::query()->with('pista')
                        ->where('torneo_id', $record->fase->categoria->torneo_id)
                        ->where('bloqueada', false)
                        ->where(fn (Builder $q) => $q->whereDoesntHave('partido')->orWhere('id', $record->franja_id))
                        ->orderBy('inicio')->orderBy('pista_id')->get()
                        ->mapWithKeys(fn (Franja $f): array => [$f->id => $f->inicio->translatedFormat('D j/n H:i').' · '.$f->pista->nombre])->all())
                    ->placeholder('Sin hora')
                    ->helperText('Solo salen las franjas libres. En blanco, el partido queda sin hora y el planificador lo volverá a colocar.'),
            ])
            ->action(function (Partido $record, array $data, Action $action): void {
                $franja = filled($data['franja_id']) ? Franja::query()->findOrFail((int) $data['franja_id']) : null;

                try {
                    app(ProgramarPartido::class)->handle($record, $franja, self::usuario());
                } catch (ErrorDeCompeticion $e) {
                    Avisos::error('No se ha podido programar', [$e->getMessage()]);
                    $action->halt();

                    return;
                }

                Avisos::hecho($franja === null ? 'Partido sin hora' : 'Partido programado', $franja?->inicio->translatedFormat('l j/n H:i'));
            });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function resultadoDesde(array $data): Resultado
    {
        $especial = filled($data['especial'] ?? null) ? ResultadoEspecial::from((string) $data['especial']) : null;
        $ganaLocal = isset($data['gana_local']) && $data['gana_local'] !== '' ? (bool) (int) $data['gana_local'] : null;

        $sets = [];
        if ($especial === null) {
            foreach ((array) ($data['sets'] ?? []) as $set) {
                if (! is_array($set) || (blank($set['juegos_local'] ?? null) && blank($set['juegos_visitante'] ?? null))) {
                    continue;
                }

                $sets[] = new SetDto(
                    (int) ($set['juegos_local'] ?? 0),
                    (int) ($set['juegos_visitante'] ?? 0),
                    filled($set['tie_break_local'] ?? null) ? (int) $set['tie_break_local'] : null,
                    filled($set['tie_break_visitante'] ?? null) ? (int) $set['tie_break_visitante'] : null,
                    (bool) ($set['es_super_tie_break'] ?? false),
                );
            }
        }

        return new Resultado($sets, $especial, $ganaLocal);
    }

    private static function usuario(): User
    {
        $usuario = Filament::auth()->user();
        assert($usuario instanceof User);

        return $usuario;
    }
}
