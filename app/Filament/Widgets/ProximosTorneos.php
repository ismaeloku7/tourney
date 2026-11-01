<?php

namespace App\Filament\Widgets;

use App\Enums\EstadoTorneo;
use App\Filament\Resources\Torneos\TorneoResource;
use App\Models\Torneo;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/** Los torneos que vienen o están en marcha, con su estado. */
class ProximosTorneos extends TableWidget
{
    protected static ?int $sort = -1;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Próximos torneos';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Torneo::query()->whereNotIn('estado', [EstadoTorneo::Finalizado->value, EstadoTorneo::Archivado->value])->orderBy('fecha_inicio'))
            ->paginated(false)
            ->emptyStateHeading('Sin torneos por delante')
            ->emptyStateDescription('Cree el primero desde Torneos.')
            ->columns([
                TextColumn::make('nombre')->label('Torneo')->url(fn (Torneo $record): string => TorneoResource::getUrl('edit', ['record' => $record])),
                TextColumn::make('fecha_inicio')->label('Empieza')->date('d/m/Y'),
                TextColumn::make('estado')->label('Estado')->badge()->formatStateUsing(fn (EstadoTorneo $state): string => $state->etiqueta())->color(fn (EstadoTorneo $state): string => $state->color()),
                TextColumn::make('categorias_count')->label('Categorías')->counts('categorias'),
            ]);
    }
}
