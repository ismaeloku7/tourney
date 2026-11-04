<?php

namespace App\Filament\Resources\Parejas;

use App\Actions\Inscripciones\MoverPareja;
use App\Competicion\Errores\ErrorDeCompeticion;
use App\Filament\Avisos;
use App\Filament\Resources\Categorias\CategoriaResource;
use App\Models\Categoria;
use App\Models\Inscripcion;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * «Mover a otra categoría»: una pareja suelta o las marcadas, a una
 * categoría de este torneo o de otro (por ejemplo, las parejas femeninas a
 * un torneo Femenino). Las que ya están sorteadas o tienen partidos no se
 * mueven; se dice cuáles y por qué.
 */
final class MoverParejas
{
    public static function accion(): Action
    {
        return Action::make('mover')
            ->label('Mover')
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->color('gray')
            ->modalHeading(fn (Inscripcion $record): string => 'Mover '.$record->participante->nombre)
            ->modalDescription('A otra categoría de este torneo o de otro. Sus jugadores, su estado y su pago van con ella.')
            ->modalSubmitActionLabel('Mover')
            ->schema([self::destino()])
            ->action(fn (Inscripcion $record, array $data, Action $action) => self::mover([$record], $data, $action));
    }

    public static function enBloque(): BulkAction
    {
        return BulkAction::make('moverMarcadas')
            ->label('Mover a otra categoría')
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->modalHeading('Mover las parejas marcadas')
            ->modalDescription('A una categoría de este torneo o de otro (por ejemplo, las femeninas a un torneo Femenino). Sus jugadores, su estado y su pago van con ellas.')
            ->modalSubmitActionLabel('Mover')
            ->schema([self::destino()])
            ->action(fn (Collection $records, array $data, BulkAction $action) => self::mover($records->all(), $data, $action))
            ->deselectRecordsAfterCompletion();
    }

    private static function destino(): Select
    {
        return Select::make('categoria_id')->label('Categoría de destino')->required()->searchable()
            ->options(function (): array {
                /** @var Builder<Categoria> $consulta */
                $consulta = CategoriaResource::getEloquentQuery();

                return $consulta->with('torneo')->orderBy('torneo_id')->orderBy('orden')->get()
                    ->groupBy(fn (Categoria $c): string => $c->torneo->nombre)
                    ->map(fn ($categorias): array => $categorias->mapWithKeys(fn (Categoria $c): array => [$c->id => $c->nombre])->all())
                    ->all();
            })
            ->helperText('Si el torneo Femenino aún no tiene categorías, créelas antes en su ficha (o duplique las de otro torneo).');
    }

    /**
     * @param  array<int, mixed>  $inscripciones
     * @param  array<string, mixed>  $data
     */
    private static function mover(array $inscripciones, array $data, Action $action): void
    {
        /** @var Builder<Categoria> $consulta */
        $consulta = CategoriaResource::getEloquentQuery();
        $destino = $consulta->find((int) ($data['categoria_id'] ?? 0));
        if (! $destino instanceof Categoria) {
            Avisos::error('Esa categoría no existe');
            $action->halt();

            return;
        }

        $usuario = Filament::auth()->user();
        $movidas = 0;
        $problemas = [];

        // Por orden de llegada: si no caben todas, la plaza es de la que se inscribió antes.
        $inscripciones = array_values(array_filter($inscripciones, fn (mixed $i): bool => $i instanceof Inscripcion));
        usort($inscripciones, fn (Inscripcion $a, Inscripcion $b): int => $a->id <=> $b->id);

        foreach ($inscripciones as $inscripcion) {

            try {
                app(MoverPareja::class)->handle($inscripcion, $destino, $usuario instanceof User ? $usuario : null);
                $movidas++;
            } catch (ErrorDeCompeticion $e) {
                $problemas[] = $e->getMessage();
            }
        }

        $donde = $destino->torneo->nombre.' · '.$destino->nombre;

        if ($movidas > 0) {
            Avisos::hecho($movidas === 1 ? "Pareja movida a {$donde}" : "{$movidas} parejas movidas a {$donde}");
        }
        if ($problemas !== []) {
            Avisos::aviso(count($problemas) === 1 ? 'Una no se ha movido' : count($problemas).' no se han movido', $problemas);
        }
    }
}
