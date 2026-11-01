<?php

namespace App\Filament;

use App\Enums\Ajuste;
use App\Models\ValorDeAjuste;
use App\Tenancy\OrganizadorActual;

/**
 * El color principal del panel y de la web del organizador: el que elige
 * en sus ajustes, o el de partida si no ha elegido o el valor guardado no
 * es un color.
 */
class ColorDelPanel
{
    public static function hex(): string
    {
        $porDefecto = (string) Ajuste::ColorPrimario->porDefecto();

        if (! app(OrganizadorActual::class)->hay()) {
            return $porDefecto;
        }

        return self::valido((string) ValorDeAjuste::obtener(Ajuste::ColorPrimario)) ?? $porDefecto;
    }

    /** `#rrggbb`, o nulo si no tiene esa forma. */
    public static function valido(string $color): ?string
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1 ? strtolower($color) : null;
    }
}
