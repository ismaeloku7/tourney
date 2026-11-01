#!/usr/bin/env bash
# Script de despliegue del sitio en Laravel Forge. Se pega en el apartado
# «Deployment script» del sitio (o se envía por la API). Vale para los
# dos modos de Forge:
#
# - Sin caída (zero downtime): Forge clona cada versión en una carpeta
#   nueva, FORGE_RELEASE_DIRECTORY, enlaza .env y storage, ejecuta este
#   script ahí y, si acaba bien, apunta `current` a la versión nueva.
# - Clásico: una sola carpeta, FORGE_SITE_PATH, que se actualiza con pull.
#
# Forge define además FORGE_SITE_BRANCH, FORGE_COMPOSER, FORGE_PHP y
# FORGE_PHP_FPM. Cualquier paso que falle corta el despliegue.
set -euo pipefail

if [ -n "${FORGE_RELEASE_DIRECTORY:-}" ]; then
    cd "$FORGE_RELEASE_DIRECTORY"
else
    cd "$FORGE_SITE_PATH"
    git pull origin "$FORGE_SITE_BRANCH"
fi

# Dependencias de PHP, exactamente las del composer.lock.
"$FORGE_COMPOSER" install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-progress

# Front: manifiesto de Vite con las versiones del package-lock.json.
npm ci --no-audit --no-fund
npm run build

# Base central y bases de los organizadores, en un solo comando. En el
# modo sin caída corre antes de que la versión nueva esté publicada.
"$FORGE_PHP" artisan actualizar

# Cachés de configuración, rutas, vistas y eventos.
"$FORGE_PHP" artisan optimize

# Los workers cargan el código nuevo en el siguiente trabajo.
"$FORGE_PHP" artisan queue:restart

( flock -w 10 9 || exit 1
    echo 'Reiniciando PHP-FPM...'
    sudo -S service "$FORGE_PHP_FPM" reload ) 9>/tmp/fpmlock
