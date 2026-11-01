<?php

namespace App\Filament;

use App\Actions\Inscripciones\ConfirmarParejasCompletas;
use App\Models\Categoria;
use App\Models\Torneo;
use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Toggle;
use Filament\Support\Icons\Heroicon;

/**
 * El botón «Confirmar las completas»: confirma de una vez las parejas
 * pendientes que ya tienen sus dos jugadores, del torneo o de una
 * categoría.
 */
final class ConfirmarCompletas
{
    /**
     * @param  Closure(): Torneo  $torneo
     * @param  Closure(): ?Categoria  $categoria
     */
    public static function accion(Closure $torneo, Closure $categoria): Action
    {
        return Action::make('confirmarCompletas')
            ->label('Confirmar las completas')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->modalHeading('Confirmar las parejas completas')
            ->modalDescription('Se confirman todas las parejas pendientes que ya tienen sus dos jugadores, por orden de llegada. Las que esperan compañero se quedan pendientes, y si una categoría llega al cupo, las que no caben también.')
            ->modalSubmitActionLabel('Confirmar')
            ->schema([
                Toggle::make('avisar')->label('Avisar a los jugadores por correo')->default(false)
                    ->helperText('Les llega «Inscripción confirmada» con la dirección de su portal.'),
            ])
            ->action(function (array $data) use ($torneo, $categoria): void {
                $usuario = Filament::auth()->user();
                $resultado = app(ConfirmarParejasCompletas::class)->handle($torneo(), $categoria(), $usuario instanceof User ? $usuario : null, (bool) ($data['avisar'] ?? false));

                $detalle = array_filter([
                    $resultado['sin_companero'] > 0 ? "{$resultado['sin_companero']} siguen pendientes porque les falta compañero." : null,
                    $resultado['sin_plaza'] > 0 ? "{$resultado['sin_plaza']} no caben en el cupo de su categoría: siguen pendientes." : null,
                ]);

                if ($resultado['confirmadas'] === 0) {
                    Avisos::aviso('No había parejas completas pendientes', array_values($detalle));

                    return;
                }

                Avisos::hecho("{$resultado['confirmadas']} parejas confirmadas", $detalle === [] ? null : implode(' ', $detalle));
            });
    }
}
