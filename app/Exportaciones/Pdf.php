<?php

namespace App\Exportaciones;

use Dompdf\Dompdf;
use Dompdf\Options;

/** Un HTML a PDF A4 con dompdf, sin recursos remotos. */
final class Pdf
{
    public static function desdeHtml(string $html, bool $horizontal = false): string
    {
        $opciones = new Options;
        $opciones->set('isRemoteEnabled', false);
        $opciones->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($opciones);
        $dompdf->setPaper('A4', $horizontal ? 'landscape' : 'portrait');
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();

        return (string) $dompdf->output();
    }
}
