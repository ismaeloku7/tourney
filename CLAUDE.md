# Tourney

Instrucciones para trabajar en este repositorio. La fuente es
`.ai/guidelines/tourney.md` (lo que Laravel Boost vuelca aquí con
`php artisan boost:update`) y la skill `.ai/skills/tourney/SKILL.md`.

- Lee `.ai/guidelines/tourney.md` y `.ai/skills/tourney/SKILL.md` antes de
  tocar nada.
- El plan vigente está en `docs/propuesta-fase-1.md`; el producto, en
  `docs/briefing.md`.
- Español en dominio, textos, comentarios y commits.
- Cada commit pasa `vendor/bin/pint`, `vendor/bin/phpstan` y
  `php artisan test` antes del push (`composer ci:check`).
- PHP 8.4 o superior.
