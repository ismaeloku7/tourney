<?php

namespace App\Enums;

use App\Enums\Concerns\ConEtiqueta;

/**
 * Grupos (round robin con clasificación), eliminatoria (cuadro),
 * consolación (cuadro de quienes no pasaron o perdieron la primera ronda)
 * y liga (un solo grupo con todos: es una fase de grupos de un grupo).
 */
enum TipoDeFase: string
{
    use ConEtiqueta;

    case Grupos = 'grupos';
    case Eliminatoria = 'eliminatoria';
    case Consolacion = 'consolacion';
    case Liga = 'liga';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Grupos => 'Fase de grupos',
            self::Eliminatoria => 'Cuadro eliminatorio',
            self::Consolacion => 'Cuadro de consolación',
            self::Liga => 'Liga',
        };
    }

    public function esDeGrupos(): bool
    {
        return $this === self::Grupos || $this === self::Liga;
    }

    public function esDeCuadro(): bool
    {
        return $this === self::Eliminatoria || $this === self::Consolacion;
    }
}
