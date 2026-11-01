<?php

namespace App\Publico;

use App\Filament\ColorDelPanel;
use App\Filament\MarcaDelOrganizador;
use GdImage;
use Illuminate\Support\Facades\Storage;

/**
 * Los iconos de la aplicación instalada, sacados del logotipo del
 * organizador: 192 y 512 para Android, el «maskable» (con margen para que
 * el móvil lo recorte en círculo) y el de iPhone (180, sin transparencia).
 * Se generan la primera vez que se piden y se guardan junto al logotipo;
 * sin logotipo, o si no se puede leer, los de partida de public/pwa.
 */
final class IconosDelOrganizador
{
    /** Nombre => [lado en píxeles, fondo de color, proporción del logo]. */
    public const TAMANOS = [
        '192' => [192, false, 1.0],
        '512' => [512, false, 1.0],
        'maskable-512' => [512, true, 0.7],
        'apple' => [180, true, 0.84],
    ];

    /** Para que los móviles pidan el icono nuevo al cambiar el logotipo. */
    public static function version(): string
    {
        return substr(md5((string) MarcaDelOrganizador::rutaDelLogo()), 0, 8);
    }

    /**
     * @return list<array{src: string, sizes: string, type: string, purpose: string}>
     */
    public static function manifiesto(): array
    {
        $v = '?v='.self::version();

        return [
            ['src' => '/icono-192.png'.$v, 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => '/icono-512.png'.$v, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => '/icono-maskable-512.png'.$v, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
        ];
    }

    /** El PNG del tamaño pedido: generado del logotipo, o nulo para usar el de partida. */
    public static function png(string $tamano): ?string
    {
        $logo = MarcaDelOrganizador::rutaDelLogo();

        if ($logo === null || ! isset(self::TAMANOS[$tamano])) {
            return null;
        }

        $disco = Storage::disk(MarcaDelOrganizador::DISCO);
        $ruta = MarcaDelOrganizador::carpeta().'/iconos/icono-'.$tamano.'-'.self::version().'.png';

        if ($disco->exists($ruta)) {
            return (string) $disco->get($ruta);
        }

        $original = @imagecreatefromstring((string) $disco->get($logo));
        if (! $original instanceof GdImage) {
            return null;
        }

        [$lado, $conFondo, $proporcion] = self::TAMANOS[$tamano];
        $png = self::icono($original, $lado, $conFondo ? ColorDelPanel::hex() : null, $proporcion);

        // Fuera los de un logotipo anterior.
        foreach ($disco->files(MarcaDelOrganizador::carpeta().'/iconos') as $viejo) {
            if (! str_contains($viejo, self::version())) {
                $disco->delete($viejo);
            }
        }
        $disco->put($ruta, $png);

        return $png;
    }

    /** El logo centrado en un cuadrado, sin deformar. */
    private static function icono(GdImage $original, int $lado, ?string $fondo, float $proporcion): string
    {
        $lado = max(1, $lado);
        $lienzo = imagecreatetruecolor($lado, $lado);
        imagealphablending($lienzo, false);
        imagesavealpha($lienzo, true);

        if ($fondo !== null) {
            [$r, $g, $b] = sscanf($fondo, '#%02x%02x%02x') ?? [255, 255, 255];
            imagefill($lienzo, 0, 0, (int) imagecolorallocate($lienzo, max(0, min(255, (int) $r)), max(0, min(255, (int) $g)), max(0, min(255, (int) $b))));
        } else {
            imagefill($lienzo, 0, 0, (int) imagecolorallocatealpha($lienzo, 0, 0, 0, 127));
        }

        imagealphablending($lienzo, true);

        $ancho = imagesx($original);
        $alto = imagesy($original);
        $hueco = (int) round($lado * $proporcion);
        $escala = min($hueco / max(1, $ancho), $hueco / max(1, $alto));
        $w = max(1, (int) round($ancho * $escala));
        $h = max(1, (int) round($alto * $escala));

        imagecopyresampled($lienzo, $original, intdiv($lado - $w, 2), intdiv($lado - $h, 2), 0, 0, $w, $h, $ancho, $alto);

        ob_start();
        imagepng($lienzo);

        return (string) ob_get_clean();
    }
}
