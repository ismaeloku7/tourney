<?php

namespace App\Enums;

use App\Enums\Concerns\ConEtiqueta;

/** De dónde sale quien ocupa un hueco del cuadro. */
enum TipoDeOrigen: string
{
    use ConEtiqueta;

    case GanadorDe = 'ganador_de';
    case PerdedorDe = 'perdedor_de';
    case PosicionDeGrupo = 'posicion_de_grupo';
    case Clasificado = 'clasificado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::GanadorDe => 'Ganador de',
            self::PerdedorDe => 'Perdedor de',
            self::PosicionDeGrupo => 'Posición de grupo',
            self::Clasificado => 'Clasificado',
        };
    }
}
