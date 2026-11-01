<?php

namespace App\Filament\Avatares;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Avatar con las iniciales, dibujado aquí mismo.
 *
 * El de Filament por defecto lo pide a ui-avatars.com con el nombre de la
 * persona en la URL: el nombre de cada miembro del equipo acabaría en un
 * servicio de fuera. Este no sale del servidor.
 */
class AvatarDeIniciales implements AvatarProvider
{
    public function get(Model $record): string
    {
        $iniciales = Str::of(Filament::getNameForDefaultAvatar($record))
            ->trim()
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn (string $palabra): string => Str::upper(Str::substr($palabra, 0, 1)))
            ->implode('');

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64">'
            .'<rect width="64" height="64" fill="#1d4ed8"/>'
            .'<text x="50%" y="50%" dy=".35em" text-anchor="middle" fill="#eff6ff" '
            .'font-family="system-ui, sans-serif" font-size="26" font-weight="600">'
            .e($iniciales)
            .'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
