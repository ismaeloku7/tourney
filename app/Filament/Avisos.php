<?php

namespace App\Filament;

use Filament\Notifications\Notification;

/** Avisos del panel: lo que el motor rechaza se enseña tal cual, con sus frases. */
final class Avisos
{
    /** @param  list<string>  $lineas */
    public static function error(string $titulo, array $lineas = []): void
    {
        Notification::make()
            ->danger()
            ->title($titulo)
            ->body($lineas === [] ? null : implode(' ', $lineas))
            ->persistent()
            ->send();
    }

    public static function hecho(string $titulo, ?string $cuerpo = null): void
    {
        Notification::make()->success()->title($titulo)->body($cuerpo)->send();
    }
}
