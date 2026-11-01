<?php

namespace App\Filament\Resources\Torneos\Pages;

use App\Enums\EstadoPartido;
use App\Filament\Competicion\AccionesDePartido;
use App\Filament\Competicion\Marcador;
use App\Filament\Resources\Torneos\TorneoResource;
use App\Models\Franja;
use App\Models\Partido;
use App\Models\Pista;
use App\Models\Torneo;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/**
 * La mesa del torneo: todos sus partidos en orden de hora, con filtros por
 * categoría, pista, día y estado, y por fila el resultado, la validación,
 * la anulación y la hora. Es lo que la organización tiene delante durante
 * el torneo.
 */
class PartidosDelTorneo extends Page implements HasTable
{
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = TorneoResource::class;

    protected static ?string $breadcrumb = 'Partidos';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        abort_unless(Gate::allows('view', $this->torneo()), 403);
    }

    public function getTitle(): string|Htmlable
    {
        return 'Partidos · '.$this->torneo()->nombre;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('torneo')
                ->label('Volver al torneo')
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('gray')
                ->url(fn (): string => TorneoResource::getUrl('edit', ['record' => $this->torneo()])),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Partido::query()
                ->whereHas('fase.categoria', fn (Builder $q) => $q->where('torneo_id', $this->torneo()->id))
                ->with(['fase.categoria', 'grupo', 'local', 'visitante', 'ganador', 'pista', 'franja', 'sets'])
                ->orderBy(Franja::query()->select('inicio')->whereColumn('franjas.id', 'partidos.franja_id'))
                ->orderBy('fase_id')->orderBy('ronda')->orderBy('jornada')->orderBy('posicion')->orderBy('id'))
            ->emptyStateHeading('Sin partidos')
            ->emptyStateDescription('Los partidos aparecen al sortear los grupos de cada categoría o al cerrar una fase que llena un cuadro.')
            ->columns([
                TextColumn::make('franja.inicio')->label('Hora')->dateTime('D j/n H:i')->placeholder('Sin hora'),
                TextColumn::make('pista.nombre')->label('Pista')->placeholder('—'),
                TextColumn::make('fase.categoria.nombre')->label('Categoría'),
                TextColumn::make('fase.nombre')->label('Fase')->description(fn (Partido $record): string => self::donde($record)),
                TextColumn::make('local')->label('Partido')
                    ->state(fn (Partido $record): string => Marcador::lado($record, true).' – '.Marcador::lado($record, false))
                    ->weight('bold')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(fn (Builder $q) => $q
                        ->whereHas('local', fn (Builder $l) => $l->where('nombre', 'like', "%{$search}%"))
                        ->orWhereHas('visitante', fn (Builder $v) => $v->where('nombre', 'like', "%{$search}%")))),
                TextColumn::make('resultado')->label('Resultado')->state(fn (Partido $record): string => Marcador::resultado($record)),
                TextColumn::make('estado')->label('Estado')->badge()
                    ->formatStateUsing(fn (EstadoPartido $state): string => $state->etiqueta())
                    ->color(fn (EstadoPartido $state): string => $state->color()),
            ])
            ->filters([
                SelectFilter::make('categoria')->label('Categoría')
                    ->options(fn (): array => $this->torneo()->categorias()->orderBy('orden')->pluck('nombre', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null) ? $query->whereHas('fase', fn (Builder $q) => $q->where('categoria_id', (int) $data['value'])) : $query),
                SelectFilter::make('pista_id')->label('Pista')
                    ->options(fn (): array => Pista::query()->whereIn('id', $this->torneo()->torneoPistas()->pluck('pista_id'))->orderBy('orden')->pluck('nombre', 'id')->all()),
                SelectFilter::make('dia')->label('Día')
                    ->options(fn (): array => collect($this->torneo()->fecha_inicio->toPeriod($this->torneo()->fecha_fin))->mapWithKeys(fn ($dia): array => [$dia->toDateString() => $dia->translatedFormat('l j \d\e F')])->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null) ? $query->whereHas('franja', fn (Builder $q) => $q->whereDate('inicio', (string) $data['value'])) : $query),
                SelectFilter::make('estado')->label('Estado')->options(EstadoPartido::opciones()),
                TernaryFilter::make('con_hora')->label('Hora')
                    ->trueLabel('Con hora')->falseLabel('Sin hora')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('franja_id'),
                        false: fn (Builder $query): Builder => $query->whereNull('franja_id'),
                    ),
            ])
            ->recordActions([
                AccionesDePartido::resultado(),
                AccionesDePartido::validar(),
                ActionGroup::make([
                    AccionesDePartido::programar(),
                    AccionesDePartido::anular(),
                ]),
            ]);
    }

    /** «Grupo A · jornada 2» o «Cuartos de final». */
    public static function donde(Partido $record): string
    {
        if ($record->grupo !== null) {
            return 'Grupo '.$record->grupo->nombre.($record->jornada !== null ? " · jornada {$record->jornada}" : '');
        }

        if ($record->ronda !== null) {
            $rondas = (int) $record->fase->partidos()->where('es_tercer_puesto', false)->max('ronda');

            return Marcador::ronda($record->ronda, $rondas, $record->es_tercer_puesto);
        }

        return '';
    }

    public function torneo(): Torneo
    {
        $torneo = $this->getRecord();
        assert($torneo instanceof Torneo);

        return $torneo;
    }
}
