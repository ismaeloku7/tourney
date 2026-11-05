<?php

namespace App\Avisos;

use App\Models\Jugador;

/**
 * Un enlace «wa.me» que abre WhatsApp en el móvil de quien lo pulsa con
 * el mensaje ya escrito para un jugador. No manda nada solo: lo manda la
 * persona desde su propio WhatsApp. Sin teléfono, no hay enlace.
 */
final class EnlaceDeWhatsApp
{
    public static function para(Jugador $jugador, string $texto): ?string
    {
        $telefono = self::numero((string) $jugador->getAttribute('telefono'));

        return $telefono === null ? null : 'https://wa.me/'.$telefono.'?text='.rawurlencode($texto);
    }

    /**
     * Solo dígitos. Un móvil o fijo español de nueve cifras sin prefijo
     * recibe el 34; cualquier otro número se deja como venga, con lo que
     * tenga de prefijo (Andorra tiene seis cifras, por ejemplo).
     */
    public static function numero(string $telefono): ?string
    {
        $digitos = preg_replace('/\D+/', '', $telefono) ?? '';
        if (str_starts_with($digitos, '00')) {
            $digitos = substr($digitos, 2);
        }
        if (strlen($digitos) === 9 && ! str_starts_with(trim($telefono), '+') && preg_match('/^[6789]/', $digitos) === 1) {
            $digitos = '34'.$digitos;
        }

        return strlen($digitos) >= 6 ? $digitos : null;
    }
}
