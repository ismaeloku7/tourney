<?php

/*
|--------------------------------------------------------------------------
| Notificaciones push
|--------------------------------------------------------------------------
|
| Las claves VAPID de la plataforma: una pareja para todos los
| organizadores. Se generan una vez con `php artisan push:claves --guardar`.
| Sin ellas, la web no ofrece las notificaciones y no se manda ninguna.
|
*/

return [
    'clave_publica' => env('WEBPUSH_VAPID_PUBLIC_KEY'),
    'clave_privada' => env('WEBPUSH_VAPID_PRIVATE_KEY'),
    'contacto' => env('WEBPUSH_VAPID_SUBJECT', 'mailto:'.env('MAIL_FROM_ADDRESS', 'hola@example.com')),
];
