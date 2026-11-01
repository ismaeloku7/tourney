<?php

namespace App\Enums;

use App\Enums\Concerns\ConEtiqueta;

/**
 * Un partido que no se jugó o no se acabó: walkover (un lado no se
 * presenta), retirada (abandono a mitad) y no presentado (ninguno de los
 * dos). Con walkover y retirada hay ganador; con no presentado, no.
 */
enum ResultadoEspecial: string
{
    use ConEtiqueta;

    case Walkover = 'walkover';
    case Retirada = 'retirada';
    case NoPresentado = 'no_presentado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Walkover => 'Walkover',
            self::Retirada => 'Retirada',
            self::NoPresentado => 'No presentado',
        };
    }

    public function tieneGanador(): bool
    {
        return $this !== self::NoPresentado;
    }
}
