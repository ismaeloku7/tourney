<?php

namespace App\Auth;

use Illuminate\Auth\SessionGuard;

/**
 * El guard de sesión de Laravel, con una diferencia: salir en un
 * dispositivo no cierra la sesión recordada en los demás. El de serie
 * cambia el «remember token» al salir, y como ese token es uno por
 * usuario, pulsar «Salir» en el móvil echaba también del ordenador.
 */
class GuardDeSesion extends SessionGuard
{
    public function logout(): void
    {
        $this->logoutCurrentDevice();
    }
}
