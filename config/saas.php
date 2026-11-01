<?php

/*
|--------------------------------------------------------------------------
| El SaaS como servicio
|--------------------------------------------------------------------------
|
| Datos del titular que exigen el aviso legal y la política de
| privacidad (LSSI y RGPD). No tienen valor por defecto a propósito: si
| faltan, las páginas legales lo dicen en vez de enseñar datos inventados.
|
*/

return [
    'titular' => [
        'nombre' => env('SAAS_TITULAR_NOMBRE'),
        'nif' => env('SAAS_TITULAR_NIF'),
        'domicilio' => env('SAAS_TITULAR_DOMICILIO'),
        'email' => env('SAAS_TITULAR_EMAIL'),
    ],
];
