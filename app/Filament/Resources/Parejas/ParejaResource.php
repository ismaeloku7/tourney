<?php

namespace App\Filament\Resources\Parejas;

use App\Enums\EstadoInscripcion;
use App\Enums\Rol;
use App\Filament\Resources\Categorias\CategoriaResource;
use App\Filament\Resources\Parejas\Pages\ListParejas;
use App\Models\Categoria;
use App\Models\Inscripcion;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

/**
 * Las parejas (los equipos) de todos los torneos, para buscarlas sin entrar
 * en cada uno: su torneo y categoría, sus jugadores con el nivel, el
 * estado y el pago. Se gestionan en su categoría. Un administrador de
 * torneo solo ve las de sus torneos.
 */
class ParejaResource extends Resource
{
    protected static ?string $model = Inscripcion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Competición';

    protected static ?int $navigationSort = 15;

    protected static ?string $slug = 'parejas';

    protected static ?string $navigationLabel = 'Parejas';

    protected static ?string $modelLabel = 'pareja';

    protected static ?string $pluralModelLabel = 'parejas';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $consulta = parent::getEloquentQuery()->with(['categoria.torneo', 'participante.jugadores']);
        $usuario = Filament::auth()->user();

        if ($usuario instanceof User && $usuario->rol === Rol::Administrador) {
            $consulta->whereHas('categoria.torneo.administradores', fn (Builder $q) => $q->whereKey($usuario->id));
        }

        return $consulta;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(50)
            ->emptyStateHeading('Sin parejas')
            ->emptyStateDescription('Se apuntan en cada categoría del torneo, se importan de un Excel desde el torneo o se inscriben ellas mismas si la inscripción online está abierta.')
            ->columns([
                TextColumn::make('categoria.torneo.nombre')->label('Torneo')->toggleable(),
                TextColumn::make('categoria.nombre')->label('Categoría')->badge()->color('gray'),
                ColumnasDePareja::nombre()
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('participante', fn (Builder $q) => $q->where(fn (Builder $o) => $o
                        ->where('nombre', 'like', "%{$search}%")
                        ->orWhereHas('jugadores', fn (Builder $j) => $j->where(fn (Builder $w) => $w
                            ->where('nombre', 'like', "%{$search}%")
                            ->orWhere('apellidos', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")))))),
                ColumnasDePareja::jugadores(),
                ColumnasDePareja::niveles(),
                ColumnasDePareja::estado(),
                ColumnasDePareja::pago(),
            ])
            ->filters([
                SelectFilter::make('torneo')->label('Torneo')
                    ->options(fn (): array => self::categorias()->mapWithKeys(fn (Categoria $c): array => [$c->torneo_id => $c->torneo->nombre])->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('categoria', fn (Builder $q) => $q->where('torneo_id', (int) $data['value']))
                        : $query),
                SelectFilter::make('categoria_id')->label('Categoría')
                    ->options(fn (): array => self::categorias()->mapWithKeys(fn (Categoria $c): array => [$c->id => $c->torneo->nombre.' · '.$c->nombre])->all()),
                SelectFilter::make('estado')->label('Estado')->options(EstadoInscripcion::opciones()),
            ])
            ->recordAction('niveles')
            ->recordActions([
                CompaneroDePareja::accion(),
                NivelesDePareja::accion(),
                MoverParejas::accion(),
                Action::make('gestionar')->label('Gestionar')->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->tooltip('Confirmar, añadir compañero, pago, baja… en su categoría')
                    ->url(fn (Inscripcion $record): string => CategoriaResource::getUrl('edit', ['record' => $record->categoria_id])),
            ])
            ->toolbarActions([
                MoverParejas::enBloque(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListParejas::route('/'),
        ];
    }

    /** @return Collection<int, Categoria> */
    private static function categorias(): Collection
    {
        /** @var Builder<Categoria> $consulta */
        $consulta = CategoriaResource::getEloquentQuery();

        return $consulta->with('torneo')->orderBy('torneo_id')->orderBy('orden')->get();
    }
}
