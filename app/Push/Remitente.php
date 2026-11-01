<?php

namespace App\Push;

use App\Models\SuscripcionPush;

/**
 * Quien entrega una notificación a un dispositivo. En producción, Web Push
 * con las claves VAPID; en las pruebas, uno que solo apunta.
 */
interface Remitente
{
    /**
     * @return bool|null true si ha llegado; false si ha fallado; null si
     *                   el dispositivo ya no existe (hay que olvidarlo).
     */
    public function enviar(SuscripcionPush $suscripcion, string $carga): ?bool;
}
