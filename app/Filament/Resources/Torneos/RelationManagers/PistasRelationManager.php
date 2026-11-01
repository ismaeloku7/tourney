<?php

namespace App\Filament\Resources\Torneos\RelationManagers;

use App\Competicion\Datos\DisponibilidadDePista;
use App\Competicion\Datos\TramoDeDisponibilidad;
use App\Competicion\Errores\ConfiguracionInvalida;
use App\Filament\Avisos;
use App\Models\Pista;
use App\Models\Torneo;
use App\Models\TorneoPista;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Las pistas en las que se juega el torneo y los tramos (día, de–a) en que
 * cada una está disponible: de aquí salen las franjas al planificar.
 */
class PistasRelationManager extends RelationManager
{
    protected static string $relationship = 'torneoPistas';

    protected static ?string $title = 'Pistas';

    protected static ?string $modelLabel = 'pista';

    protected static ?string $pluralModelLabel = 'pistas';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Select::make('pista_id')->label('Pista')->required()->searchable()->preload()
                    ->options(fn (?TorneoPista $record): array => Pista::query()->with('sede')->where('activa', true)
                        ->whereNotIn('id', $this->torneo()->torneoPistas()->whereKeyNot($record->id ?? 0)->pluck('pista_id'))
                        ->orderBy('sede_id')->orderBy('orden')->orderBy('nombre')->get()
                        ->mapWithKeys(fn (Pista $pista): array => [$pista->id => ($pista->sede?->nombre !== null ? $pista->sede->nombre.' · ' : '').$pista->nombre])->all()),
                TextInput::make('orden')->label('Orden')->integer()->minValue(0)->default(fn (): int => $this->torneo()->torneoPistas()->count() + 1)
                    ->helperText('Las primeras se llenan antes al planificar.'),
                Repeater::make('disponibilidad')->label('Disponibilidad')
                    ->addActionLabel('Añadir tramo')
                    ->columns(3)
                    ->default(fn (): array => self::disponibilidadPorDefecto($this->torneo()))
                    ->schema([
                        DatePicker::make('fecha')->label('Día')->required()->native(false)->displayFormat('d/m/Y')
                            ->minDate(fn (): string => $this->torneo()->fecha_inicio->toDateString())
                            ->maxDate(fn (): string => $this->torneo()->fecha_fin->toDateString()),
                        TimePicker::make('desde')->label('Desde')->required()->seconds(false),
                        TimePicker::make('hasta')->label('Hasta')->required()->seconds(false),
                    ])
                    ->helperText('Un tramo por día y pista. Dos tramos el mismo día dejan un hueco entre ellos (comida, por ejemplo).'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('orden')
            ->reorderable('orden')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('pista.sede'))
            ->emptyStateHeading('Sin pistas')
            ->emptyStateDescription('Añada las pistas en las que se juega y cuándo están libres. Sin ellas no se pueden planificar horarios.')
            ->columns([
                TextColumn::make('orden')->label('Orden'),
                TextColumn::make('pista.nombre')->label('Pista')->weight('bold'),
                TextColumn::make('pista.sede.nombre')->label('Sede')->placeholder('—'),
                TextColumn::make('tramos')->label('Disponibilidad')->state(fn (TorneoPista $record): string => self::resumen($record)),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Añadir pista')
                    ->modalHeading('Pista del torneo')
                    ->using(fn (array $data, Action $action): Model => $this->getRelationship()->create(self::comprobar($data, $action))),
                Action::make('anadirLasDeLaSede')
                    ->label('Añadir todas las de la sede')
                    ->icon(Heroicon::OutlinedSquares2x2)
                    ->color('gray')
                    ->visible(fn (): bool => $this->pistasDeLaSedeQueFaltan()->isNotEmpty())
                    ->requiresConfirmation()
                    ->modalDescription(fn (): string => 'Se añaden las pistas activas de la sede que aún no están en el torneo, disponibles de 09:00 a 22:00 cada día del torneo. Luego se ajusta cada una.')
                    ->action(function (): void {
                        $torneo = $this->torneo();
                        $orden = $torneo->torneoPistas()->count();
                        $faltan = $this->pistasDeLaSedeQueFaltan();

                        foreach ($faltan as $pista) {
                            $torneo->torneoPistas()->create(['pista_id' => $pista->id, 'orden' => ++$orden, 'disponibilidad' => self::disponibilidadPorDefecto($torneo)]);
                        }

                        Avisos::hecho($faltan->count() === 1 ? 'Una pista añadida' : "{$faltan->count()} pistas añadidas");
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (TorneoPista $record, array $data, Action $action): TorneoPista {
                        $record->update(self::comprobar($data, $action));

                        return $record;
                    }),
                DeleteAction::make()->label('Quitar'),
            ]);
    }

    /**
     * De 09:00 a 22:00 cada día del torneo: lo normal en un club; se ajusta
     * después.
     *
     * @return list<array{fecha: string, desde: string, hasta: string}>
     */
    public static function disponibilidadPorDefecto(Torneo $torneo): array
    {
        $tramos = [];

        foreach ($torneo->fecha_inicio->toPeriod($torneo->fecha_fin) as $dia) {
            $tramos[] = ['fecha' => $dia->toDateString(), 'desde' => '09:00', 'hasta' => '22:00'];
        }

        return $tramos;
    }

    /** «3 días · 39 h» */
    public static function resumen(TorneoPista $torneoPista): string
    {
        try {
            $disponibilidad = DisponibilidadDePista::desdeArray($torneoPista->disponibilidad);
        } catch (ConfiguracionInvalida) {
            return 'Disponibilidad incompleta';
        }

        $dias = count(array_unique(array_map(fn (TramoDeDisponibilidad $tramo): string => $tramo->fecha, $disponibilidad->tramos)));
        $minutos = array_sum(array_map(fn (TramoDeDisponibilidad $tramo): int => self::minutos($tramo->hasta) - self::minutos($tramo->desde), $disponibilidad->tramos));
        $horas = $minutos % 60 === 0 ? (string) intdiv($minutos, 60) : number_format($minutos / 60, 1, ',', '');

        return ($dias === 1 ? '1 día' : "{$dias} días")." · {$horas} h";
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function comprobar(array $data, Action $action): array
    {
        $tramos = array_values(array_map(fn (mixed $tramo): array => [
            'fecha' => (string) ($tramo['fecha'] ?? ''),
            'desde' => substr((string) ($tramo['desde'] ?? ''), 0, 5),
            'hasta' => substr((string) ($tramo['hasta'] ?? ''), 0, 5),
        ], (array) ($data['disponibilidad'] ?? [])));

        try {
            $data['disponibilidad'] = DisponibilidadDePista::desdeArray($tramos)->toArray();
        } catch (ConfiguracionInvalida $e) {
            Avisos::error('La disponibilidad no es válida', $e->errores);
            $action->halt();
        }

        return $data;
    }

    private static function minutos(string $hora): int
    {
        [$h, $m] = array_map('intval', explode(':', $hora));

        return $h * 60 + $m;
    }

    /** @return Collection<int, Pista> */
    private function pistasDeLaSedeQueFaltan(): Collection
    {
        $torneo = $this->torneo();

        if ($torneo->sede_id === null) {
            return new Collection;
        }

        return Pista::query()->where('sede_id', $torneo->sede_id)->where('activa', true)
            ->whereNotIn('id', $torneo->torneoPistas()->pluck('pista_id'))
            ->orderBy('orden')->orderBy('nombre')->get();
    }

    private function torneo(): Torneo
    {
        $torneo = $this->getOwnerRecord();
        assert($torneo instanceof Torneo);

        return $torneo;
    }
}
