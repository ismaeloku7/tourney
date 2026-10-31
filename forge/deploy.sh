#!/usr/bin/env bash
# Script de despliegue del sitio en Laravel Forge. Se pega en el apartado
# «Deployment script» del sitio (o se envía por la API). Forge define
# FORGE_SITE_PATH, FORGE_SITE_BRANCH, FORGE_COMPOSER, FORGE_PHP y
# FORGE_PHP_FPM. Cualquier paso que falle corta el despliegue.
set -euo pipefail

cd "$FORGE_SITE_PATH"

git pull origin "$FORGE_SITE_BRANCH"

# Dependencias de PHP, exactamente las del composer.lock.
"$FORGE_COMPOSER" install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-progress

# Front: manifiesto de Vite con las versiones del package-lock.json.
npm ci --no-audit --no-fund
npm run build

# Mantenimiento solo durante las migraciones; la landing y las páginas
# públicas vuelven en segundos.
"$FORGE_PHP" artisan down --retry=10 || true

# Base central y bases de los organizadores, en un solo comando.
"$FORGE_PHP" artisan actualizar

# Cachés de configuración, rutas, vistas y eventos.
"$FORGE_PHP" artisan optimize

"$FORGE_PHP" artisan up

# Los workers cargan el código nuevo en el siguiente trabajo.
"$FORGE_PHP" artisan queue:restart

( flock -w 10 9 || exit 1
    echo 'Reiniciando PHP-FPM...'
    sudo -S service "$FORGE_PHP_FPM" reload ) 9>/tmp/fpmlock
