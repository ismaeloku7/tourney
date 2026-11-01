<?php

namespace App\Enums\Concerns;

/**
 * Para los enums con `etiqueta()`: las opciones de un desplegable,
 * valor → etiqueta, en el orden de los casos.
 */
trait ConEtiqueta
{
    /** @return array<string, string> */
    public static function opciones(): array
    {
        $opciones = [];

        foreach (self::cases() as $caso) {
            $opciones[$caso->value] = $caso->etiqueta();
        }

        return $opciones;
    }
}
