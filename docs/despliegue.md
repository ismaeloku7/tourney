# Despliegue en Laravel Forge

Un servidor con Forge, un sitio para el dominio de la plataforma y sus
subdominios comodín, una base MySQL central y una base por organizador
que la aplicación crea sola. Misma pauta que CEAS. Lo que va en el
repositorio ya está: [`forge/deploy.sh`](../forge/deploy.sh) es el script
de despliegue y `php artisan actualizar` lo que se ejecuta tras cada uno.

## Servidor

- Ubuntu 24.04 con PHP 8.4 y MySQL 8 (en Forge, servidor «App» o «Web»
  con la base en el mismo servidor; con servidores separados vale igual).
- El usuario de MySQL del sitio necesita el privilegio `CREATE DATABASE`:
  cada organizador tiene su propia base (`organizador_{slug}`), creada al
  darle de alta y borrada al destruirlo. El usuario `forge` lo tiene; uno
  creado desde el panel de Forge para una sola base, no.
- Node 22 (Forge lo instala con el servidor) para `npm run build`.
- Un worker de cola y el programador (más abajo).

## Sitio

| Campo de Forge | Valor |
| --- | --- |
| Root domain | El dominio de la plataforma, `SAAS_DOMINIO` (por ejemplo `tourney.clubceas.es`) |
| Aliases | `*.tourney.clubceas.es` (y `tourney.on-forge.com` como alias), o la opción «Wildcard sub-domains» del sitio |
| Project type | Laravel (`public` como web directory) |
| PHP version | 8.4 |
| Repository | `ismaeloku7/tourney`, rama `main`, sin «Install composer dependencies» (lo hace el script) |
| Quick deploy | Activado en `main` |

Dominio propio de un organizador (fase 4): se añade como alias del mismo
sitio y se emite su certificado; el host resuelve al organizador por su
fila en `domains`.

## Con el dominio gratuito `on-forge.com`

Forge da a cada sitio un dominio `tourney.on-forge.com` con certificado
automático. Sirve para la plataforma (landing, `/plataforma`) y para
desplegar desde el primer día, pero **no para los organizadores**: sus
subdominios `{slug}.tourney.on-forge.com` necesitan un registro DNS
comodín y un certificado comodín, y el DNS de `on-forge.com` lo lleva
Forge, sin comodín para un sitio ni credenciales de DNS que darle.

Mientras se use solo ese dominio:

- `SAAS_DOMINIO=tourney.on-forge.com`, `APP_URL=https://tourney.on-forge.com`
  y `SESSION_DOMAIN=null`.
- Las páginas de un organizador no se pueden abrir desde fuera. La fase 1
  no tiene interfaz, así que no bloquea nada; en la fase 2 (inscripción
  pública y panel del organizador) hace falta el dominio propio de abajo.

Cuando llegue ese momento: un dominio de la plataforma con el DNS en
Cloudflare (gratis) u otro proveedor con API, los registros `A` del
dominio y de `*.dominio` a la IP del servidor, el dominio y `*.dominio`
como dominios del sitio, y el certificado comodín del apartado siguiente.
El sitio `on-forge.com` puede seguir como alias.

## Certificado

Comodín para `tourney.clubceas.es` y `*.tourney.clubceas.es` con LetsEncrypt. Forge
necesita validar por DNS, así que el DNS del dominio tiene que estar en
un proveedor con API que Forge conozca (Cloudflare, DigitalOcean, Route53,
Linode, OVH…) y hay que darle sus credenciales en el apartado SSL del
sitio. Si el DNS está en otro sitio, se mueve a Cloudflare (gratis) o se
sube un certificado comodín propio.

DNS: un registro `A` para `tourney.clubceas.es` y otro `A` para `*.tourney.clubceas.es`,
los dos a la IP del servidor.

El dominio de la plataforma es un subdominio de uno que ya existe:
`tourney.clubceas.es` y `*.tourney.clubceas.es`. En Cloudflare, los dos registros con el proxy
**desactivado** (nube gris, «DNS only»): el certificado universal de
Cloudflare solo cubre un nivel de subdominio, así que con el proxy
activado `demo.tourney.clubceas.es` daría error de certificado aunque el
de Forge esté bien. El certificado lo pone Forge en el servidor con la
API de Cloudflare (un token con permiso de edición de DNS en esa zona).

## Variables de entorno

Lo que cambia respecto a [`.env.example`](../.env.example). El resto
queda igual. Nada de esto va al repositorio.

```dotenv
APP_NAME=Tourney
APP_ENV=production
APP_KEY=                        # php artisan key:generate --show
APP_DEBUG=false
APP_URL=https://tourney.clubceas.es

SAAS_DOMINIO=tourney.clubceas.es
SAAS_TITULAR_NOMBRE=
SAAS_TITULAR_NIF=
SAAS_TITULAR_DOMICILIO=
SAAS_TITULAR_EMAIL=

# En producción no hay hosts de desarrollo.
ORGANIZADORES_HOSTS_DESARROLLO=
ORGANIZADORES_PREFIJO_BASE=organizador_

LOG_CHANNEL=daily
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tourney             # la base central
DB_USERNAME=forge
DB_PASSWORD=

SESSION_DRIVER=database
SESSION_DOMAIN=.tourney.clubceas.es     # la sesión vale en el dominio y sus subdominios
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=database

MAIL_MAILER=resend
RESEND_API_KEY=
MAIL_FROM_ADDRESS=hola@clubceas.es
MAIL_FROM_NAME="${APP_NAME}"

# php artisan push:claves, una vez, cuando llegue la PWA.
WEBPUSH_VAPID_PUBLIC_KEY=
WEBPUSH_VAPID_PRIVATE_KEY=
WEBPUSH_VAPID_SUBJECT=mailto:hola@clubceas.es
```

La base central `tourney` se crea desde el apartado Database de Forge.
Las de los organizadores no: las crea la aplicación.

## Script de despliegue

El contenido de [`forge/deploy.sh`](../forge/deploy.sh), pegado en
«Deployment script». Hace, en este orden: `git pull`, `composer install`
sin dependencias de desarrollo, `npm ci` y `npm run build`, modo
mantenimiento, `php artisan actualizar` (migra la central y cada
organizador), `php artisan optimize`, fin del mantenimiento,
`queue:restart` y recarga de PHP-FPM. Si un paso falla, el despliegue se
corta y Forge lo marca en rojo.

## Cola y programador

- Queue, en el sitio: un worker con la conexión `database`, cola
  `default`, `--tries=3 --timeout=90`, un proceso. Los correos y los
  avisos push salen por aquí; sin worker no sale nada.
- Scheduler, en el servidor: `php artisan schedule:run` cada minuto, con
  el PHP 8.4 del sitio, para las tareas de `routes/console.php`.

## Después del primer despliegue

1. `php artisan key:generate --show` en local y pegar la clave en
   `APP_KEY`; o generarla en el servidor desde «Commands».
2. El primer usuario de plataforma, cuando llegue la base central:
   `php artisan plataforma:administrador`.
3. Copias de seguridad: cada organizador es una base distinta. Forge las
   hace de todas las bases del servidor si se marca «All databases» en el
   apartado Backups, o con un volcado por base en un cron.

## Con la API de Forge

Todo lo de arriba se puede hacer sin el panel con la [API de
Forge](https://forge.laravel.com/api-documentation) (v2, por
organización) y un token creado en el perfil, apartado API. En las
sesiones de Claude el token vive en la variable de entorno
`FORGE_API_TOKEN` y la red del entorno tiene que permitir
`forge.laravel.com`. Con eso se crea el sitio, se sube el `.env`, el
script de despliegue, el certificado, el worker y el programador, y se
lanzan despliegues.
