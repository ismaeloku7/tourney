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

| Pieza | Versión en `composer.json` | Papel |
| --- | --- | --- |
| PHP | `^8.4` | Lo exigen Symfony 8 y Pest 5 |
| Laravel | `^13.17` | Núcleo, colas, correo, programador |
| Livewire | `^4.1` | Front público, inscripción, resultados en vivo |
| Filament | `^5.0` | Panel del organizador (`/panel`) y de plataforma (`/plataforma`) |
| stancl/tenancy | `^3.10` | Una base por organizador, resuelta por host |
| minishlink/web-push | `^11.0` | Notificaciones push de la PWA |
| dompdf | `^3.1` | Cuadros, horarios y clasificaciones en PDF |
| Tailwind + Vite | `^4`, `vite-plus` | Front público |
| Pest, Pint, Larastan | `^5.3`, `^1.27`, `^3.9` | Pruebas, formato, tipos (nivel 7) |

Las versiones se fijarán en `composer.lock` y `package-lock.json` en la
primera instalación, a partir de las de CEAS de octubre de 2026.

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

## Estructura prevista

Cada organizador tendrá su propia base de datos
([stancl/tenancy](https://tenancyforlaravel.com)); la central guarda los
organizadores, sus dominios, planes y usuarios de plataforma. El
organizador se elige por el host: su subdominio del SaaS
(`demo.saas.localhost` en local, con `SAAS_DOMINIO`) o su dominio propio.
En local se puede abrir en `localhost` gracias a
`ORGANIZADORES_HOSTS_DESARROLLO`.

`php artisan migrate` solo migra la base central; las de los
organizadores irán con `php artisan tenants:migrate`.

El motor de competición vive en `app/Competicion`: clases puras, sin
Eloquent, con cobertura total en `tests/Unit/Competicion`.

## Estado

Fase 1, commit 1 de 6 ([orden de commits](docs/propuesta-fase-1.md#orden-de-commits)):
proyecto base. Pendiente generar `composer.lock` y `package-lock.json`
con la primera instalación.
