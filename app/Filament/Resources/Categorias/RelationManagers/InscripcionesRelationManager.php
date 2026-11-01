<?php

namespace App\Filament\Resources\Categorias\RelationManagers;

use App\Actions\Inscripciones\CambiarEstadoDeInscripcion;
use App\Actions\Inscripciones\CompletarPareja;
use App\Actions\Inscripciones\FijarCabezaDeSerie;
use App\Actions\Inscripciones\InscribirPareja;
use App\Actions\Inscripciones\MarcarPago;
use App\Competicion\Errores\ErrorDeCompeticion;
use App\Enums\EstadoInscripcion;
use App\Enums\Genero;
use App\Enums\OrigenInscripcion;
use App\Filament\Avisos;
use App\Filament\ConfirmarCompletas;
use App\Models\Categoria;
use App\Models\Inscripcion;
use App\Models\Jugador;
use App\Models\Torneo;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Las parejas de la categoría: la organización las apunta a mano (con
 * jugadores de la base o nuevos), las confirma, las pasa a la lista de
 * espera o las da de baja, anota el pago y fija cabezas de serie.
 */
class InscripcionesRelationManager extends RelationManager
{
    protected static string $relationship = 'inscripciones';

    protected static ?string $title = 'Inscripciones';

    protected static ?string $modelLabel = 'inscripción';

    protected static ?string $pluralModelLabel = 'inscripciones';

    public function table(Table $table): Table
    {
        return $table
            ->heading(fn (): string => $this->titulo())
            ->defaultSort('id')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['participante.jugadores']))
            ->emptyStateHeading('Sin parejas')
            ->emptyStateDescription('Apunte las parejas a mano, o abra la inscripción online del torneo cuando esté lista (fase 3).')
            ->columns([
                TextColumn::make('participante.nombre')->label('Pareja')->searchable()->weight('bold'),
                TextColumn::make('jugadores')->label('Jugadores')
                    ->state(fn (Inscripcion $record): array => $record->participante->jugadores->map(fn (Jugador $j): string => $j->nombre_completo)->all())
                    ->listWithLineBreaks(),
                TextColumn::make('niveles')->label('Nivel')
                    ->state(fn (Inscripcion $record): array => $record->participante->jugadores->map(fn (Jugador $j): string => $j->nivel_declarado ?? '—')->all())
                    ->listWithLineBreaks()
                    ->description(fn (Inscripcion $record): ?string => $record->participante->nivelMedioTexto()),
                TextColumn::make('estado')->label('Estado')->badge()
                    ->formatStateUsing(fn (EstadoInscripcion $state, Inscripcion $record): string => $state->etiqueta().($record->posicion_espera !== null ? " (#{$record->posicion_espera})" : ''))
                    ->color(fn (EstadoInscripcion $state): string => $state->color()),
                TextColumn::make('participante.cabeza_de_serie')->label('Cabeza de serie')->badge()->color('warning')->placeholder('—')
                    ->formatStateUsing(fn (?int $state): string => $state === null ? '' : "CS {$state}"),
                IconColumn::make('pago_marcado_en')->label('Pago')->boolean()->trueIcon(Heroicon::OutlinedBanknotes)->falseIcon(Heroicon::OutlinedMinus)
                    ->state(fn (Inscripcion $record): bool => $record->pago_marcado_en !== null)
                    ->tooltip(fn (Inscripcion $record): ?string => $record->pago_notas),
                TextColumn::make('origen')->label('Origen')->formatStateUsing(fn (OrigenInscripcion $state): string => $state->etiqueta())->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label('Apuntada')->dateTime('d/m/Y H:i')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('estado')->label('Estado')->options(EstadoInscripcion::opciones()),
            ])
            ->headerActions([
                ConfirmarCompletas::accion(fn (): Torneo => $this->categoria()->torneo, fn (): Categoria => $this->categoria()),
                Action::make('inscribir')
                    ->label('Inscribir pareja')
                    ->icon(Heroicon::OutlinedUserPlus)
                    ->modalHeading('Inscribir una pareja')
                    ->modalSubmitActionLabel('Inscribir')
                    ->schema([
                        self::selectorDeJugador('jugador_1_id', 'Jugador 1', 'jugador_2_id'),
                        self::selectorDeJugador('jugador_2_id', 'Jugador 2', 'jugador_1_id'),
                        Toggle::make('confirmada')->label('Confirmada')->default(true)->inline(false)
                            ->helperText('Desactivada, queda pendiente hasta que la confirme. Si la categoría está al cupo, va a la lista de espera.'),
                        Textarea::make('notas')->label('Notas')->rows(2)->maxLength(2000),
                    ])
                    ->action(function (array $data, Action $action): void {
                        $jugador1 = Jugador::query()->findOrFail((int) $data['jugador_1_id']);
                        $jugador2 = Jugador::query()->findOrFail((int) $data['jugador_2_id']);

                        try {
                            $inscripcion = app(InscribirPareja::class)->handle($this->categoria(), $jugador1, $jugador2, OrigenInscripcion::Manual, $this->usuario(), (bool) $data['confirmada'], $data['notas'] ?? null);
                        } catch (ErrorDeCompeticion $e) {
                            Avisos::error('No se ha podido inscribir', [$e->getMessage()]);
                            $action->halt();

                            return;
                        }

                        Avisos::hecho(match ($inscripcion->estado) {
                            EstadoInscripcion::ListaDeEspera => "Pareja en lista de espera (puesto {$inscripcion->posicion_espera})",
                            EstadoInscripcion::Pendiente => 'Pareja apuntada, pendiente de confirmar',
                            default => 'Pareja inscrita',
                        }, $inscripcion->participante->nombre);
                    }),
            ])
            ->recordActions([
                Action::make('companero')->label('Añadir compañero')->icon(Heroicon::OutlinedUserPlus)->color('warning')
                    ->visible(fn (Inscripcion $record): bool => $record->participante->jugadores->count() < 2)
                    ->modalHeading(fn (Inscripcion $record): string => 'Compañero de '.$record->participante->nombre)
                    ->modalSubmitActionLabel('Añadir')
                    ->schema([
                        self::selectorDeJugador('companero_id', 'Compañero'),
                        Toggle::make('confirmar')->label('Confirmar la inscripción')->default(true)->inline(false)
                            ->helperText('Si la categoría está al cupo, va a la lista de espera.'),
                    ])
                    ->action(function (Inscripcion $record, array $data, Action $action): void {
                        try {
                            app(CompletarPareja::class)->handle($record, Jugador::query()->findOrFail((int) $data['companero_id']), (bool) ($data['confirmar'] ?? true), $this->usuario());
                        } catch (ErrorDeCompeticion $e) {
                            Avisos::error('No se ha podido completar la pareja', [$e->getMessage()]);
                            $action->halt();

                            return;
                        }

                        Avisos::hecho('Pareja completa');
                    }),
                ActionGroup::make([
                    Action::make('confirmar')->label('Confirmar')->icon(Heroicon::OutlinedCheck)->color('success')
                        ->visible(fn (Inscripcion $record): bool => in_array($record->estado, [EstadoInscripcion::Pendiente, EstadoInscripcion::ListaDeEspera], true))
                        ->action(fn (Inscripcion $record, Action $action) => $this->cambiarEstado($record, EstadoInscripcion::Confirmada, $action)),
                    Action::make('esperar')->label('Pasar a lista de espera')->icon(Heroicon::OutlinedClock)->color('info')
                        ->visible(fn (Inscripcion $record): bool => in_array($record->estado, [EstadoInscripcion::Pendiente, EstadoInscripcion::Confirmada], true))
                        ->action(fn (Inscripcion $record, Action $action) => $this->cambiarEstado($record, EstadoInscripcion::ListaDeEspera, $action)),
                    Action::make('baja')->label('Dar de baja')->icon(Heroicon::OutlinedXMark)->color('danger')
                        ->visible(fn (Inscripcion $record): bool => $record->estado !== EstadoInscripcion::Baja)
                        ->requiresConfirmation()
                        ->modalDescription('La pareja deja la categoría. Si ya hubo sorteo, sus partidos pendientes quedarán como no presentados al cerrarlos.')
                        ->action(fn (Inscripcion $record, Action $action) => $this->cambiarEstado($record, EstadoInscripcion::Baja, $action)),
                    Action::make('readmitir')->label('Readmitir')->icon(Heroicon::OutlinedArrowUturnLeft)
                        ->visible(fn (Inscripcion $record): bool => $record->estado === EstadoInscripcion::Baja)
                        ->action(fn (Inscripcion $record, Action $action) => $this->cambiarEstado($record, EstadoInscripcion::Pendiente, $action)),
                    Action::make('pago')->label('Anotar pago')->icon(Heroicon::OutlinedBanknotes)
                        ->fillForm(fn (Inscripcion $record): array => ['pagado' => $record->pago_marcado_en !== null, 'pago_notas' => $record->pago_notas])
                        ->schema([
                            Toggle::make('pagado')->label('Pagado')->inline(false),
                            TextInput::make('pago_notas')->label('Notas del pago')->maxLength(255)->placeholder('Bizum 12/04, efectivo…'),
                        ])
                        ->action(function (Inscripcion $record, array $data): void {
                            app(MarcarPago::class)->handle($record, (bool) $data['pagado'], $data['pago_notas'] ?? null, $this->usuario());
                            Avisos::hecho('Pago anotado');
                        }),
                    Action::make('cabezaDeSerie')->label('Cabeza de serie')->icon(Heroicon::OutlinedStar)->color('warning')
                        ->visible(fn (Inscripcion $record): bool => $record->estado === EstadoInscripcion::Confirmada)
                        ->fillForm(fn (Inscripcion $record): array => ['numero' => $record->participante->cabeza_de_serie])
                        ->schema([
                            TextInput::make('numero')->label('Número')->integer()->minValue(1)->maxValue(128)->helperText('En blanco, deja de ser cabeza de serie. El 1 encabeza el grupo A o el cuadro.'),
                        ])
                        ->action(function (Inscripcion $record, array $data, Action $action): void {
                            try {
                                app(FijarCabezaDeSerie::class)->handle($record->participante, filled($data['numero']) ? (int) $data['numero'] : null, $this->usuario());
                            } catch (ErrorDeCompeticion $e) {
                                Avisos::error('No se ha podido fijar', [$e->getMessage()]);
                                $action->halt();

                                return;
                            }

                            Avisos::hecho('Cabeza de serie actualizada');
                        }),
                ]),
            ]);
    }

    private static function selectorDeJugador(string $campo, string $etiqueta, ?string $distintoDe = null): Select
    {
        return Select::make($campo)->label($etiqueta)->required()->searchable()
            ->when($distintoDe !== null, fn (Select $select): Select => $select->different((string) $distintoDe))
            ->options(fn (): array => Jugador::query()->orderBy('apellidos')->orderBy('nombre')->get()->mapWithKeys(fn (Jugador $j): array => [$j->id => $j->nombre_completo])->all())
            ->createOptionForm([
                TextInput::make('nombre')->label('Nombre')->required()->maxLength(80),
                TextInput::make('apellidos')->label('Apellidos')->required()->maxLength(120),
                TextInput::make('email')->label('Correo electrónico')->email()->maxLength(190)->unique(Jugador::class, 'email')
                    ->helperText('Con su correo puede entrar en su portal para ver sus partidos y le llegan los avisos. Sin correo, no.'),
                TextInput::make('telefono')->label('Teléfono')->tel()->maxLength(30),
                Select::make('genero')->label('Género')->options(Genero::opciones())->placeholder('Sin indicar'),
            ])
            ->createOptionModalHeading('Jugador nuevo')
            ->createOptionUsing(fn (array $data): int => Jugador::query()->create($data)->id);
    }

    private function cambiarEstado(Inscripcion $inscripcion, EstadoInscripcion $estado, Action $action): void
    {
        try {
            app(CambiarEstadoDeInscripcion::class)->handle($inscripcion, $estado, $this->usuario());
        } catch (ErrorDeCompeticion $e) {
            Avisos::error('No se ha podido cambiar la inscripción', [$e->getMessage()]);
            $action->halt();

            return;
        }

        Avisos::hecho('Inscripción '.mb_strtolower($estado->etiqueta()));
    }

    private function titulo(): string
    {
        $categoria = $this->categoria();
        $confirmadas = $categoria->inscripciones()->where('estado', EstadoInscripcion::Confirmada->value)->count();
        $espera = $categoria->inscripciones()->where('estado', EstadoInscripcion::ListaDeEspera->value)->count();

        return "Inscripciones · {$confirmadas} confirmadas".($categoria->cupo !== null ? " de {$categoria->cupo}" : '').($espera > 0 ? " · {$espera} en espera" : '');
    }

    private function categoria(): Categoria
    {
        $categoria = $this->getOwnerRecord();
        assert($categoria instanceof Categoria);

        return $categoria;
    }

    private function usuario(): User
    {
        $usuario = Filament::auth()->user();
        assert($usuario instanceof User);

        return $usuario;
    }
}
