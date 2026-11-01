# Despliegue en Laravel Forge

Un servidor con Forge, un sitio con el dominio de la plataforma y los
dominios propios de los organizadores, una base MySQL central y una base
por organizador que la aplicación crea sola. Misma pauta que CEAS. Lo que va en el
repositorio ya está: [`forge/deploy.sh`](../forge/deploy.sh) es el script
de despliegue y `php artisan actualizar` lo que se ejecuta tras cada uno.

## Servidor

- Ubuntu 24.04 con PHP 8.4 y MySQL 8 (en Forge, servidor «App» o «Web»
  con la base en el mismo servidor; con servidores separados vale igual).
- El usuario de MySQL del sitio tiene que poder crear y borrar bases:
  cada organizador tiene la suya (`organizador_{slug}`), creada al darle
  de alta y borrada al destruirlo. El usuario `forge` puede; uno creado
  desde el panel de Forge para una sola base (`tourney`), no, y el alta
  se rompe a medias con «Access denied … to database 'organizador_x'».
  Para seguir con un usuario propio, se le dan permisos solo sobre las
  bases de organizador, con la contraseña del usuario `forge` (está en el
  `.env` de cualquier sitio del servidor que lo use):

  ```bash
  mysql -h 127.0.0.1 -u forge -p -e "GRANT ALL PRIVILEGES ON \`organizador\_%\`.* TO 'tourney'@'%'; FLUSH PRIVILEGES;"
  ```

  Un alta que falló a medias deja la fila sin dominios (`tenants:list`
  la muestra con `@` vacío): se quita con `organizadores:destruir` y se
  repite.
- Node 22 (Forge lo instala con el servidor) para `npm run build`.
- Un worker de cola y el programador (más abajo).

## Dominios

Dos tipos de host, los dos en el mismo sitio de Forge:

| Host | Qué es | Certificado |
| --- | --- | --- |
| `tourney.on-forge.com` | La plataforma: landing, registro, `/plataforma`. Es `SAAS_DOMINIO` | El que Forge da al dominio gratuito |
| `tourney.clubceas.es` | Un organizador concreto (el CEAS) por su dominio propio | LetsEncrypt normal, validado por HTTP |

Cada organizador se resuelve por el host (`ResolverOrganizadorPorHost`):
un dominio propio se busca en la tabla `domains` de la base central. La
pantalla para que un organizador configure su dominio es de la fase 4;
hasta entonces la fila la pone el seeder o el comando
`organizadores:crear … --dominio=`.

Con este modelo no hace falta ningún registro DNS comodín ni certificado
comodín. Lo que no da es el alta self-service con subdominio automático
(`{slug}.tourney.on-forge.com`), porque el DNS de `on-forge.com` lo lleva
Forge y no admite comodín. Cuando se abra el registro público, la
plataforma pasará a un dominio propio con `*.dominio` (DNS en Cloudflare
u otro proveedor con API, para que Forge emita el certificado comodín);
`SAAS_DOMINIO` admite varios dominios separados por comas, así que
`tourney.on-forge.com` puede seguir funcionando a la vez.

### Añadir el dominio propio de un organizador

1. En el DNS del organizador, un registro `A` del host (`tourney` en
   `clubceas.es`) a la IP del servidor. En Cloudflare, proxy desactivado
   (nube gris).
2. En Forge, sitio de Tourney, Domains: añadir el host. SSL: certificado
   LetsEncrypt solo para ese host, que se valida por HTTP, sin
   credenciales de DNS. Al activarlo Forge escribe el bloque de nginx con
   `listen 443 ssl`.
3. La fila en `domains`, con el comando de crear organizador o, para uno
   que ya existe, desde el panel de plataforma (fase 4).

Un host sin certificado activo en nuestro sitio lo captura cualquier otro
sitio del servidor con un comodín que lo cubra (el del CEAS tiene
`server_name .clubceas.es`): el síntoma es un 404 de la otra aplicación.

## Sitio

| Campo de Forge | Valor |
| --- | --- |
| Root domain | `tourney.on-forge.com` |
| Domains | `tourney.clubceas.es` y los dominios propios de cada organizador |
| Project type | Laravel (`public` como web directory) |
| PHP version | 8.4 |
| Repository | `ismaeloku7/tourney`, rama `main` (hasta el primer pull request, `claude/amazing-darwin-w4lzfs`) |
| Deployment | Sin caída (zero downtime); el script de abajo vale para los dos modos |
| Quick deploy | Activado |

## Variables de entorno

Lo que cambia respecto a [`.env.example`](../.env.example). El resto
queda igual. Nada de esto va al repositorio.

```dotenv
APP_NAME=Tourney
APP_ENV=production
APP_KEY=                        # php artisan key:generate --show
APP_DEBUG=false
APP_URL=https://tourney.on-forge.com

SAAS_DOMINIO=tourney.on-forge.com
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
SESSION_DOMAIN=null             # cookie de cada host: hay dominios propios de organizadores
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=database

# El alta self-service manda el correo de verificación con este remitente.
MAIL_MAILER=resend
RESEND_API_KEY=
MAIL_FROM_ADDRESS=hola@clubceas.es
MAIL_FROM_NAME="${APP_NAME}"

# Los logotipos de los organizadores van al disco público:
# storage/app/public, servido en /storage (el script hace storage:link).
FILESYSTEM_DISK=local

# php artisan push:claves, una vez, cuando llegue la PWA.
WEBPUSH_VAPID_PUBLIC_KEY=
WEBPUSH_VAPID_PRIVATE_KEY=
WEBPUSH_VAPID_SUBJECT=mailto:hola@clubceas.es
```

La base central `tourney` se crea desde el apartado Database de Forge.
Las de los organizadores no: las crea la aplicación.

## Script de despliegue

El contenido de [`forge/deploy.sh`](../forge/deploy.sh), pegado en
«Deployment script». Vale para el modo sin caída de Forge (cada versión
en su carpeta, `FORGE_RELEASE_DIRECTORY`, y `current` apuntando a la
última que acabó bien) y para el clásico (`git pull` en la carpeta del
sitio). Hace, en este orden: `composer install` sin dependencias de
desarrollo, `npm ci` y `npm run build`, `php artisan actualizar` (migra la
central y cada organizador), `php artisan optimize`, `queue:restart` y
recarga de PHP-FPM. Si un paso falla, el despliegue se corta, Forge lo
marca en rojo y, en el modo sin caída, la versión anterior sigue
sirviendo.

## Cola y programador

- Queue, en el sitio: un worker con la conexión `database`, cola
  `default`, `--tries=3 --timeout=90`, un proceso. Los correos y los
  avisos push salen por aquí; sin worker no sale nada.
- Scheduler, en el servidor: `php artisan schedule:run` cada minuto, con
  el PHP 8.4 del sitio, para las tareas de `routes/console.php`.

## Después del primer despliegue

1. `php artisan key:generate --show` en local y pegar la clave en
   `APP_KEY`; o generarla en el servidor desde «Commands».
2. El primer usuario de plataforma, desde Commands:
   `php artisan plataforma:administrador`.
3. El organizador CEAS con su dominio propio, desde Commands. Crea su
   base `organizador_ceas` (el usuario `forge` de MySQL puede crear bases),
   su subdominio, el dominio y el propietario, y enseña la contraseña una
   sola vez si no se pasa `--clave`:

   ```bash
   php artisan organizadores:crear ceas "Club CEAS" padel@clubceas.es --dominio=tourney.clubceas.es --plan=club
   ```

   Desde entonces `https://tourney.clubceas.es` es la portada del CEAS y
   `https://tourney.on-forge.com` sigue siendo la plataforma.
4. Copias de seguridad: cada organizador es una base distinta. Forge las
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
