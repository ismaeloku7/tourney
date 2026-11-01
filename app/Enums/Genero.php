<?php

namespace App\Enums;

use App\Enums\Concerns\ConEtiqueta;

enum Genero: string
{
    use ConEtiqueta;

    case Masculino = 'masculino';
    case Femenino = 'femenino';
    case Mixto = 'mixto';
    case Abierto = 'abierto';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Masculino => 'Masculino',
            self::Femenino => 'Femenino',
            self::Mixto => 'Mixto',
            self::Abierto => 'Abierto',
        };
    }
}
