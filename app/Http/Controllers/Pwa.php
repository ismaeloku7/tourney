<?php

namespace App\Http\Controllers;

use App\Filament\ColorDelPanel;
use App\Filament\MarcaDelOrganizador;
use App\Publico\IconosDelOrganizador;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * La aplicación instalable de cada organizador: su manifiesto (nombre,
 * color e icono suyos, abre en su portada) y sus iconos. Públicos: el
 * móvil los pide sin sesión.
 */
class Pwa
{
    public function manifiesto(): JsonResponse
    {
        $nombre = MarcaDelOrganizador::nombre();

        return response()->json([
            'name' => $nombre,
            'short_name' => Str::limit($nombre, 12, ''),
            'description' => "Torneos de {$nombre}: inscripción, horarios, cuadros y resultados en directo.",
            'lang' => 'es',
            'dir' => 'ltr',
            'id' => '/',
            'start_url' => '/?origen=app',
            'scope' => '/',
            'display' => 'standalone',
            'theme_color' => ColorDelPanel::hex(),
            'background_color' => '#ffffff',
            'icons' => IconosDelOrganizador::manifiesto(),
        ], options: JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ->header('Content-Type', 'application/manifest+json; charset=utf-8')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    public function icono(string $tamano): Response
    {
        abort_unless(isset(IconosDelOrganizador::TAMANOS[$tamano]), 404);

        $cabeceras = ['Content-Type' => 'image/png', 'Cache-Control' => 'public, max-age=86400', 'X-Content-Type-Options' => 'nosniff'];
        $png = IconosDelOrganizador::png($tamano);

        return $png === null
            ? response()->file(public_path('pwa/icono-'.$tamano.'.png'), $cabeceras)
            : response($png, 200, $cabeceras);
    }
}
