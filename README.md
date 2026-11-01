# Tourney

SaaS multi-tenant de gestión de torneos de pádel, al estilo de Xporty:
cada organizador se da de alta por sí mismo, crea sus torneos y los
publica con inscripción online, cuadros, horarios y resultados en vivo.
Se entrega como PWA.

Mismo stack y convenciones que
[clubexploradoressaas](https://github.com/ismaeloku7/clubexploradoressaas)
(CEAS): Laravel 13, Livewire 4, Filament 5, Tailwind 4, stancl/tenancy,
Pest, Pint y PHPStan; código en español, hora de Madrid, papelera y
seeder con una cuenta por rol.

- [docs/briefing.md](docs/briefing.md): briefing funcional completo, con
  el roadmap en cuatro fases y los criterios de aceptación.
- [docs/propuesta-fase-1.md](docs/propuesta-fase-1.md): esquema de
  tablas, motor de competición, acciones, seeder y tests de la fase 1, y
  el orden de commits que se sigue.
- [CLAUDE.md](CLAUDE.md), [.ai/guidelines](.ai/guidelines) y
  [.ai/skills](.ai/skills): reglas para trabajar en el repositorio.

## Stack

Versiones fijadas el 31 de octubre de 2026 en [`composer.lock`](composer.lock)
y [`package-lock.json`](package-lock.json), resueltas para PHP 8.4
(`config.platform` de `composer.json`):

| Pieza | Versión | Papel |
| --- | --- | --- |
| PHP | `^8.4` | Lo exigen Symfony 8 y Pest 5 |
| Laravel | 13.39 | Núcleo, colas, correo, programador |
| Livewire | 4.4 | Front público, inscripción, resultados en vivo |
| Filament | 5.11 | Panel del organizador (`/panel`) y de plataforma (`/plataforma`) |
| stancl/tenancy | 3.10 | Una base por organizador, resuelta por host |
| minishlink/web-push | 11.0 | Notificaciones push de la PWA |
| dompdf | 3.1 | Cuadros, horarios y clasificaciones en PDF |
| Tailwind + Vite | 4.3, Vite 8 (`vite-plus`) | Front público |
| Pest, Pint, Larastan | 5.3, 1.32, 3.13 | Pruebas, formato, tipos (nivel 7) |

## Instalación

Hace falta PHP 8.4, Composer y Node 22.

```bash
composer run setup
```

Instala las dependencias de PHP y Node, copia `.env.example` si no hay
`.env`, genera `APP_KEY` y aplica las migraciones de la base central. La
base de ejemplo es SQLite.

Arranque local:

```bash
composer run dev
```

Lo mismo que la integración continua (Pint, PHPStan y Pest):

```bash
composer ci:check
```

## Estructura

Cada organizador tiene su propia base de datos
([stancl/tenancy](https://tenancyforlaravel.com)); la central guarda los
organizadores, sus dominios, planes, usuarios de plataforma, métricas de
uso y auditoría. El organizador se elige por el host
(`ResolverOrganizadorPorHost`): su subdominio del SaaS
(`demo.saas.localhost` en local, con `SAAS_DOMINIO`) o su dominio
propio. El dominio del SaaS no es de ningún organizador; un host
desconocido responde 404. En local se puede abrir en `localhost` gracias
a `ORGANIZADORES_HOSTS_DESARROLLO`, que no cuenta fuera de `local` y
`testing`.

`php artisan migrate` solo migra la base central. Las de los
organizadores (`database/migrations/tenant`):

```bash
php artisan tenants:migrate
```

Tras cada despliegue, un solo comando hace las dos cosas; es el que va
en el script de Forge:

```bash
php artisan actualizar
```

`php artisan db:seed` siembra los tres planes, el usuario de plataforma
`admin@example.com` y el organizador `demo` («Club Demo», plan Club) con
su base `database/organizador_demo.sqlite` y su propietario
`propietario@example.com`, todos con la clave `password`. Repetirlo no
duplica nada.

Un organizador real se da de alta desde la consola mientras no exista el
alta self-service:

```bash
php artisan organizadores:crear ceas "Club CEAS" padel@clubceas.es --dominio=tourney.clubceas.es --plan=club
```

Crea su fila central, su base migrada, su subdominio, su dominio propio y
su propietario; si no se pasa `--clave`, genera una y la enseña una sola
vez. `php artisan organizadores:destruir ceas --confirmar` lo borra con
su base. El primer usuario de la plataforma se crea con
`php artisan plataforma:administrador`.

El motor de competición vive en `app/Competicion`: clases puras, sin
Eloquent, que reciben y devuelven DTOs de `app/Competicion/Datos`
(validador de resultados, reparto de grupos, round robin,
clasificación, clasificados, cuadro, consolación, avance y
planificador), con sus pruebas en `tests/Unit/Competicion`. Las acciones
de `app/Actions/Competicion` (sortear, generar partidos y franjas,
planificar, registrar, validar y anular resultados, cerrar fase, cambiar
el estado del torneo) unen el motor con los modelos dentro de una
transacción y dejan rastro en `registro_de_cambios`.

`php artisan db:seed` deja además en `demo` un torneo por estado:
`open-demo-sorteo` (sorteado y planificado), `open-demo-en-juego` (con
resultados a medias) y `open-demo-finalizado` (cuadro de 8 con mejores
terceros, tercer puesto y consolación, todo jugado).

## Despliegue

En un VPS con Laravel Forge, con DNS comodín para los subdominios de los
organizadores y MySQL con permiso para crear una base por organizador.
Los pasos, las variables de entorno y el script de despliegue
(`forge/deploy.sh`, que llama a `php artisan actualizar`) están en
[docs/despliegue.md](docs/despliegue.md).

## Estado

Fase 1 completa y en verde en CI ([orden de commits](docs/propuesta-fase-1.md#orden-de-commits)):
proyecto base, base central con tenancy, modelo de dominio del
organizador, DTOs, los nueve servicios del motor de competición y las
acciones que unen motor y modelos, con el seeder de un torneo por estado.

Fase 2 en marcha: el panel del organizador en `/panel` (Filament 5) con
el equipo, las instalaciones, los formatos, los jugadores y los torneos;
cada categoría tiene su ficha con las fases (validadas por el motor), las
pistas del torneo con su disponibilidad y las inscripciones a mano
(parejas con jugadores de la base o nuevos, cupo con lista de espera,
bajas, pago y cabezas de serie). La competición se lleva desde el panel:
estado del torneo, sorteo de grupos, planificación de horarios, mesa de
partidos con resultados, validación, anulación y hora a mano, cierre de
fases con el cuadro, y la clasificación y el cuadro a la vista. Los
ajustes de marca (nombre, logotipo, colores, contacto y textos legales),
las exportaciones (categoría y horarios en PDF, inscritos en CSV) y el
alta self-service en el dominio de la plataforma, con verificación por
correo, cierran la fase 2.

Fase 3 en marcha: la web pública de cada organizador ya tiene su portada
con los torneos publicados, la ficha de cada torneo (datos, categorías
con plazas, inscritos, grupos, cuadro y horarios, con alias si el torneo
lo pide), el tablero para la tele y la página de cada pista con su QR. Se
refrescan solas mientras el torneo está en juego. Faltan la inscripción
online, el portal del jugador, la mesa de árbitros, la PWA y los avisos.

`composer ci:check` (Pint, PHPStan nivel 7 y Pest) corre en GitHub
Actions en cada pull request y en `main`.
