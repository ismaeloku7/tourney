<?php

/*
|--------------------------------------------------------------------------
| Organizadores
|--------------------------------------------------------------------------
|
| `hosts_desarrollo` relaciona un host local con el slug de un organizador,
| para poder abrir la aplicación en `localhost` sin tocar el dominio del
| organizador. Formato: "localhost=demo,127.0.0.1=demo". Solo se tiene en
| cuenta en los entornos `local` y `testing`.
|
*/

$hosts = [];

foreach (array_filter(explode(',', (string) env('ORGANIZADORES_HOSTS_DESARROLLO', ''))) as $par) {
    [$host, $slug] = array_pad(array_map('trim', explode('=', $par, 2)), 2, '');

    if ($host !== '' && $slug !== '') {
        $hosts[strtolower($host)] = $slug;
    }
}

return [
    'hosts_desarrollo' => $hosts,

    /*
    | Slugs que no puede tomar ningún organizador: son subdominios de la
    | plataforma o inducen a confusión.
    */
    'slugs_reservados' => ['www', 'admin', 'administrador', 'plataforma', 'api', 'app', 'mail', 'correo', 'smtp', 'ftp', 'saas', 'soporte', 'ayuda', 'estado', 'static', 'cdn', 'test', 'pruebas'],
];
