<?php
// Autoload PSR-4 mínimo para App\ sin Composer: solo vale para el código
// puro (App\Competicion y App\Enums), que no depende de Laravel.
spl_autoload_register(function (string $clase): void {
    if (str_starts_with($clase, 'App\\')) {
        $ruta = __DIR__.'/../../app/'.str_replace('\\', '/', substr($clase, 4)).'.php';
        if (is_file($ruta)) {
            require $ruta;
        }
    }
});
