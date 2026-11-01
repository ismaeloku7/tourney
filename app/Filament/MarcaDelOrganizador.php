<?php

namespace App\Filament;

use App\Enums\Ajuste;
use App\Models\ValorDeAjuste;
use App\Tenancy\OrganizadorActual;
use Illuminate\Support\Facades\Storage;

/**
 * La marca del organizador actual tal y como se enseña: nombre público,
 * logotipo (en el disco público) y colores. Todo sale de sus ajustes.
 */
final class MarcaDelOrganizador
{
    public const DISCO = 'public';

    public static function nombre(): string
    {
        $actual = app(OrganizadorActual::class);

        if (! $actual->hay()) {
            return config()->string('app.name');
        }

        $publico = ValorDeAjuste::obtener(Ajuste::NombrePublico);

        return is_string($publico) && trim($publico) !== '' ? $publico : $actual->organizador()->nombre;
    }

    public static function rutaDelLogo(): ?string
    {
        if (! app(OrganizadorActual::class)->hay()) {
            return null;
        }

        $ruta = ValorDeAjuste::obtener(Ajuste::LogoPath);

        return is_string($ruta) && $ruta !== '' && Storage::disk(self::DISCO)->exists($ruta) ? $ruta : null;
    }

    public static function urlDelLogo(): ?string
    {
        $ruta = self::rutaDelLogo();

        return $ruta === null ? null : Storage::disk(self::DISCO)->url($ruta);
    }

    /** La carpeta del organizador en el disco público. */
    public static function carpeta(): string
    {
        return 'organizadores/'.app(OrganizadorActual::class)->organizador()->id;
    }

    public static function colorSecundario(): ?string
    {
        $color = ValorDeAjuste::obtener(Ajuste::ColorSecundario);

        return is_string($color) ? ColorDelPanel::valido($color) : null;
    }
}
