<?php

namespace App\Filament\Resources\Jugadores\Pages;

use App\Filament\Resources\Jugadores\JugadorResource;
use App\Models\Jugador;
use App\Models\SuscripcionPush;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListJugadores extends ListRecords
{
    protected static string $resource = JugadorResource::class;

    /** Cuántos reciben avisos en el móvil, y cuántos dispositivos siguen torneos sin cuenta. */
    public function getSubheading(): string
    {
        $activadas = Jugador::conNotificacionesActivadas(Jugador::query())->count();
        $sinCuenta = SuscripcionPush::query()->whereNull('jugador_id')->whereNull('user_id')->count();

        $texto = match ($activadas) {
            0 => 'Ningún jugador tiene activadas las notificaciones en el móvil.',
            1 => '1 jugador tiene activadas las notificaciones en el móvil.',
            default => "{$activadas} jugadores tienen activadas las notificaciones en el móvil.",
        };

        if ($sinCuenta > 0) {
            $texto .= ' '.($sinCuenta === 1 ? '1 dispositivo sigue' : "{$sinCuenta} dispositivos siguen").' torneos sin cuenta.';
        }

        return $texto.' Filtra por «Notificaciones en el móvil» para ver quiénes.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
