<?php

namespace App\Enums;

enum Genero: string
{
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
