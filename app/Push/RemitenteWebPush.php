<?php

namespace App\Push;

use App\Models\SuscripcionPush;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Web Push estándar (RFC 8030 y 8291): el mismo para Android, iPhone
 * (con la app en la pantalla de inicio) y los navegadores de ordenador.
 * Sin Firebase ni cuentas de terceros: solo las claves VAPID. Copiado de
 * CEAS.
 */
class RemitenteWebPush implements Remitente
{
    private ?WebPush $webPush = null;

    public function enviar(SuscripcionPush $suscripcion, string $carga): ?bool
    {
        $informe = $this->webPush()->sendOneNotification(
            Subscription::create([
                'endpoint' => $suscripcion->endpoint,
                'publicKey' => $suscripcion->clave_p256dh,
                'authToken' => $suscripcion->clave_auth,
                'contentEncoding' => 'aes128gcm',
            ]),
            $carga,
            // Un día: un aviso de más de un día ya no es aviso.
            ['TTL' => 86400, 'urgency' => 'normal'],
        );

        if ($informe->isSuccess()) {
            return true;
        }

        return $informe->isSubscriptionExpired() ? null : false;
    }

    private function webPush(): WebPush
    {
        return $this->webPush ??= new WebPush(['VAPID' => [
            'subject' => config()->string('webpush.contacto'),
            'publicKey' => config()->string('webpush.clave_publica'),
            'privateKey' => config()->string('webpush.clave_privada'),
        ]]);
    }
}
