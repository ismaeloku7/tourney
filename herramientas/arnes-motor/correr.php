<?php
/*
 * Ejecuta tests/Unit/Competicion con PHP a secas, sin Composer ni Laravel:
 *
 *   php herramientas/arnes-motor/correr.php
 *
 * Sirve para comprobar el motor de competición en un entorno sin las
 * dependencias instaladas. No sustituye a `php artisan test`: el arnés
 * imita solo la parte de Pest que usan esas pruebas.
 */
require __DIR__.'/autoload.php';
require __DIR__.'/pest-shim.php';

$archivos = $argv[1] ?? __DIR__.'/../../tests/Unit/Competicion/*.php';

exit(ejecutarPruebas(glob($archivos)));
