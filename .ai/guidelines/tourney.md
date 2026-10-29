# Tourney

SaaS multi-tenant de gestión de torneos de pádel, al estilo de Xporty,
en Laravel 13 + Filament 5 + Livewire 4 + stancl/tenancy. Mismo stack y
mismas convenciones que `clubexploradoressaas` (CEAS).

Lee antes de tocar nada:

- `.ai/skills/tourney/SKILL.md` — reglas de dominio y de arquitectura.
- `docs/briefing.md` — briefing funcional, roadmap en cuatro fases y
  criterios de aceptación.
- `docs/propuesta-fase-1.md` — esquema de tablas, enums, DTOs, servicios
  del motor, acciones, seeder y tests de la fase 1, y el orden de commits.

## Reglas que no se discuten

- Español en dominio, textos, comentarios y commits. Trato de usted en
  los textos para jugadores y organizadores.
- El motor de competición (`app/Competicion`) es puro: sin Eloquent, sin
  `now()`, sin `config()`, sin sesión. Recibe DTOs y devuelve DTOs. Todo
  lo aleatorio recibe una semilla.
- Nunca se crea ni se sube un `.env` ni un volcado con datos reales.
- Una migración ya mergeada no se edita: se añade otra.
- Todo cambio va por rama y pull request.
- Cada commit pasa `vendor/bin/pint`, `vendor/bin/phpstan` y
  `php artisan test` antes del push.

## Comandos

- `composer run setup` — instalación completa.
- `composer run dev` — servidor, cola, logs y Vite.
- `php artisan tenants:migrate` — migraciones de las bases de organizador
  (`database/migrations/tenant`); `migrate` solo hace la central.
- `vendor/bin/pint --dirty --format agent` — formato tras tocar PHP.
- `php artisan test --compact` — suite Pest.
- `composer ci:check` — lo mismo que CI: Pint, PHPStan, tests.
- `npm run build` — manifiesto de Vite.

PHP 8.4 o superior: el `composer.lock` lo exige.
