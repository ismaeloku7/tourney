<?php

namespace App\Enums;

use App\Enums\Concerns\ConEtiqueta;

enum ModoDeSorteo: string
{
    use ConEtiqueta;

    case Aleatorio = 'aleatorio';
    case CabezasDeSerie = 'cabezas_de_serie';
    case Manual = 'manual';
    case PorNiveles = 'por_niveles';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Aleatorio => 'Aleatorio',
            self::CabezasDeSerie => 'Con cabezas de serie',
            self::Manual => 'Manual',
            self::PorNiveles => 'Por niveles (bombos)',
        };
    }
}
