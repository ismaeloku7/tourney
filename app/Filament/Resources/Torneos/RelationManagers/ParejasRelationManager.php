<?php

namespace App\Filament\Resources\Torneos\RelationManagers;

use App\Enums\EstadoInscripcion;
use App\Filament\ConfirmarCompletas;
use App\Filament\Resources\Parejas\AccesoDePareja;
use App\Filament\Resources\Parejas\ColumnasDePareja;
use App\Filament\Resources\Parejas\CompaneroDePareja;
use App\Filament\Resources\Parejas\ConVistaParaCopiar;
use App\Filament\Resources\Parejas\MoverParejas;
use App\Filament\Resources\Parejas\ParaCopiar;
use App\Filament\Resources\Parejas\ParejaResource;
use App\Filament\Resources\Parejas\SeCopiaParaPegar;
use App\Models\Categoria;
use App\Models\Inscripcion;
use App\Models\Torneo;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Todas las parejas del torneo de un vistazo, con su categoría. Para
 * confirmarlas, completarlas o darlas de baja se entra en su categoría.
 */
class ParejasRelationManager extends RelationManager implements SeCopiaParaPegar
{
    use ConVistaParaCopiar;

    /** En la ficha del torneo (de solo lectura) esta pestaña se sigue gestionando. */
    public function isReadOnly(): bool
    {
        return false;
    }

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
                ->with(['categoria', 'participante.jugadores', 'accesoDirecto'])
                ->orderBy('categorias.orden')
                ->orderBy('inscripciones.id'))
            ->paginated([25, 50, 100, 'all'])
            ->defaultPaginationPageOption(50)
            ->emptyStateHeading('Sin parejas')
            ->emptyStateDescription('Se apuntan dentro de cada categoría (pestaña Categorías › Configurar › Inscripciones), con «Importar parejas» desde un Excel o, si la inscripción online está abierta, las propias parejas.')
            ->columns([
                TextColumn::make('categoria.nombre')->label('Categoría')->badge()->color('gray')->toggleable(),
                ColumnasDePareja::nombre(),
                ColumnasDePareja::jugadores(),
                ColumnasDePareja::niveles(),
                ColumnasDePareja::estado(),
                ColumnasDePareja::pago(),
            ])
            ->filters([
                SelectFilter::make('categoria')->label('Categoría')
                    ->options(fn (): array => $this->torneo()->categorias()->pluck('nombre', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null) ? $query->where('inscripciones.categoria_id', (int) $data['value']) : $query),
                SelectFilter::make('estado')->label('Estado')->options(EstadoInscripcion::opciones())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null) ? $query->where('inscripciones.estado', (string) $data['value']) : $query),
            ])
            ->headerActions([
                ParaCopiar::copiarParaWhatsApp(),
                ParaCopiar::boton(),
                AccesoDePareja::crearTodos(),
                AccesoDePareja::borrarTodos(),
                ConfirmarCompletas::accion(fn (): Torneo => $this->torneo(), fn (): ?Categoria => null),
            ])
            ->recordUrl(fn (Inscripcion $record, mixed $livewire): ?string => ParaCopiar::enlace($livewire, ParejaResource::getUrl('edit', ['record' => $record])))
            ->recordActions(ParaCopiar::ocultando([
                AccesoDePareja::crear(),
                AccesoDePareja::ver(),
                CompaneroDePareja::accion(),
                MoverParejas::accion(),
                Action::make('editar')->label('Editar')->icon(Heroicon::OutlinedPencilSquare)
                    ->url(fn (Inscripcion $record): string => ParejaResource::getUrl('edit', ['record' => $record])),
            ]))
            ->toolbarActions(ParaCopiar::ocultando([
                MoverParejas::enBloque(),
            ]));
    }

    private function torneo(): Torneo
    {
        $torneo = $this->getOwnerRecord();
        assert($torneo instanceof Torneo);

        return $torneo;
    }
}
