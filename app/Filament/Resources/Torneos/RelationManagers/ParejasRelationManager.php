<?php

namespace App\Filament\Resources\Torneos\RelationManagers;

use App\Enums\EstadoInscripcion;
use App\Filament\ConfirmarCompletas;
use App\Filament\Resources\Categorias\CategoriaResource;
use App\Models\Categoria;
use App\Models\Inscripcion;
use App\Models\Jugador;
use App\Models\Torneo;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Todas las parejas del torneo de un vistazo, con su categoría. Para
 * confirmarlas, completarlas o darlas de baja se entra en su categoría.
 */
class ParejasRelationManager extends RelationManager
{
    protected static string $relationship = 'inscripciones';

    protected static ?string $title = 'Parejas';

    protected static ?string $modelLabel = 'pareja';

    protected static ?string $pluralModelLabel = 'parejas';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        assert($ownerRecord instanceof Torneo);
        $total = $ownerRecord->inscripciones()->where('inscripciones.estado', '!=', EstadoInscripcion::Baja->value)->count();

        return $total > 0 ? (string) $total : null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['categoria', 'participante.jugadores'])
                ->orderBy('categorias.orden')
                ->orderBy('inscripciones.id'))
            ->paginated([25, 50, 100, 'all'])
            ->defaultPaginationPageOption(50)
            ->emptyStateHeading('Sin parejas')
            ->emptyStateDescription('Se apuntan dentro de cada categoría (pestaña Categorías › Configurar › Inscripciones), con «Importar parejas» desde un Excel o, si la inscripción online está abierta, las propias parejas.')
            ->columns([
                TextColumn::make('categoria.nombre')->label('Categoría')->badge()->color('gray'),
                TextColumn::make('participante.nombre')->label('Pareja')->searchable()->weight('bold'),
                TextColumn::make('jugadores')->label('Jugadores')
                    ->state(fn (Inscripcion $record): array => $record->participante->jugadores->map(fn (Jugador $j): string => $j->nombre_completo)->all())
                    ->listWithLineBreaks(),
                TextColumn::make('niveles')->label('Nivel')
                    ->state(fn (Inscripcion $record): array => $record->participante->jugadores->map(fn (Jugador $j): string => $j->nivel_declarado ?? '—')->all())
                    ->listWithLineBreaks()
                    ->description(fn (Inscripcion $record): ?string => $record->participante->nivelMedioTexto()),
                TextColumn::make('estado')->label('Estado')->badge()
                    ->formatStateUsing(fn (EstadoInscripcion $state, Inscripcion $record): string => $state->etiqueta()
                        .($record->participante->jugadores->count() < 2 ? ' · falta compañero' : '')
                        .($record->posicion_espera !== null ? " (#{$record->posicion_espera})" : ''))
                    ->color(fn (EstadoInscripcion $state): string => $state->color()),
                IconColumn::make('pago_marcado_en')->label('Pago')->boolean()->trueIcon(Heroicon::OutlinedBanknotes)->falseIcon(Heroicon::OutlinedMinus)
                    ->state(fn (Inscripcion $record): bool => $record->pago_marcado_en !== null),
            ])
            ->filters([
                SelectFilter::make('categoria')->label('Categoría')
                    ->options(fn (): array => $this->torneo()->categorias()->pluck('nombre', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null) ? $query->where('inscripciones.categoria_id', (int) $data['value']) : $query),
                SelectFilter::make('estado')->label('Estado')->options(EstadoInscripcion::opciones())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null) ? $query->where('inscripciones.estado', (string) $data['value']) : $query),
            ])
            ->headerActions([
                ConfirmarCompletas::accion(fn (): Torneo => $this->torneo(), fn (): ?Categoria => null),
            ])
            ->recordActions([
                Action::make('gestionar')->label('Gestionar')->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->tooltip('Confirmar, añadir compañero, pago, baja… en su categoría')
                    ->url(fn (Inscripcion $record): string => CategoriaResource::getUrl('edit', ['record' => $record->categoria_id])),
            ]);
    }

    private function torneo(): Torneo
    {
        $torneo = $this->getOwnerRecord();
        assert($torneo instanceof Torneo);

        return $torneo;
    }
}
