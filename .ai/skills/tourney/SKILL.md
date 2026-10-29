---
name: tourney
description: Reglas de dominio y de arquitectura del SaaS de torneos de pádel Tourney (multi-organizador, Laravel 13 + Filament 5 + Livewire 4 + stancl/tenancy). Úsala siempre que escribas, revises o expliques código de este repositorio que toque organizadores, torneos, categorías, inscripciones, grupos, cuadros, horarios, resultados, el motor de competición o la tenancy.
---

# Tourney — reglas de dominio

Las skills genéricas de Laravel, PHP y seguridad de `.ai/skills` siguen
valiendo. Esta añade solo lo propio del producto. Si una regla de aquí
choca con una genérica, gana esta.

Documentos de referencia, por este orden:

1. `docs/briefing.md` — qué es el producto, actores, modelo de dominio,
   configuración del torneo, motor, flujos, PWA, planes, roadmap.
2. `docs/propuesta-fase-1.md` — tablas, enums, DTOs, servicios, acciones,
   seeder, tests y orden de commits de la fase 1. Lo que dice ahí es lo
   acordado; si se cambia, se anota allí.

## Convenciones heredadas de CEAS

- Tablas, columnas, clases, métodos y variables en español. Migraciones
  con un comentario de cabecera que explica la tabla.
- `deleted_at` (`VaALaPapelera` + `ReglasDePapelera`) en las tablas del
  organizador que guardan cosas; no en pivotes ni en registros.
- Enums en `App\Enums`, `string`, con `etiqueta()`. Las columnas de estado
  son `string` cortos con un enum detrás.
- Acciones en `App\Actions\<Área>\<Verbo>.php` con `handle()`. Filament y
  Livewire solo las llaman; la lógica no vive en la interfaz.
- Dominio puro en carpetas de primer nivel de `app/` (`App\Competicion`).
- Pest 5. `RefreshDatabase` en la base central; cada prueba que necesita
  un organizador crea su base y la borra al acabar (`TestCase`).
- Pint con preset `laravel`. PHPStan nivel 7 con Larastan. CI con
  `composer ci:check`.
- Zona `Europe/Madrid`, locale `es`, `es_ES` para Faker. Las cadenas de
  interfaz pasan por `__()` desde el principio.
- Seeder con una cuenta por rol (`propietario@`, `administrador@`,
  `arbitro@`, `jugador@example.com`, clave `password`) y el usuario de
  plataforma `admin@example.com`. Se puede volver a sembrar sin duplicar.

## Tenancy: una base por organizador (stancl/tenancy)

- Base **central**: `organizadores` (el tenant, `App\Models\Organizador`,
  id = slug), `domains`, `planes`, `suscripciones_plan`,
  `usuarios_plataforma` (guard `plataforma`), `eventos_uso`,
  `auditoria_global`, sesiones, caché y colas. Base **del organizador**
  (`organizador_{slug}`): todo lo demás, sin `organizador_id`. Migraciones
  en `database/migrations/tenant`, con `php artisan tenants:migrate`.
- El organizador se elige por el **host** (`ResolverOrganizadorPorHost`):
  `{slug}.<SAAS_DOMINIO>` o su dominio propio. Nunca por query, formulario
  ni cabecera. Host desconocido u organizador no activo → 404. En el
  dominio central no hay organizador. En local y testing,
  `ORGANIZADORES_HOSTS_DESARROLLO` relaciona `localhost` con un slug.
- Los modelos de la base del organizador usan
  `App\Tenancy\EnLaBaseDelOrganizador`: sin organizador activo lanzan
  `SinOrganizadorActual` en vez de buscar en la central.
- Solo el middleware y `OrganizadorActual::ejecutarComo()` cambian de
  organizador (comandos, colas). En pruebas: `comoOrganizador()` y
  `usarOrganizador()`.
- Identidad (slug, nombre, estado, plan, dominios) en la central; marca,
  zona horaria y textos legales en la tabla `ajustes` del organizador
  (enum `Ajuste`).
- Los límites de plan se comprueban contando en la base del organizador en
  el mismo request. `eventos_uso` es para métricas, no para límites.
- Las claves de caché y de rate limiter que dependen del organizador
  llevan su slug: la caché es central y compartida.
- Archivos subidos: disco `local`, carpeta `organizadores/{slug}/…`,
  servidos por una ruta con Policy, nunca desde `public/`.

## Motor de competición (`App\Competicion`)

- Clases `final readonly`, sin Eloquent, sin `now()`, sin `config()`, sin
  sesión. Entradas y salidas son DTOs de `App\Competicion\Datos`. Los ids
  de participante son `int` opacos.
- Todo lo aleatorio recibe una semilla y usa `mt_srand($semilla)`
  localmente: el mismo sorteo se repite.
- Se lanza una excepción de `App\Competicion\Errores` (todas extienden
  `ErrorDeCompeticion`) solo ante una entrada imposible. Lo que es una
  decisión del organizador (grupos desiguales, conflictos de horario)
  vuelve como aviso en el DTO de salida. El `Planificador` nunca lanza.
- El motor no conoce el deporte: las reglas de pádel entran por
  `FormatoDePartido` y por la `Puntuacion` de la fase.
- Servicios, en este orden de implementación: `ValidadorDeResultado`,
  `RepartidorDeGrupos`, `GeneradorRoundRobin`, `CalculadorDeClasificacion`,
  `SelectorDeClasificados`, `ConstructorDeCuadro`,
  `ConstructorDeConsolacion`, `AvanceDeCuadro`, `Planificador`. Cada uno
  con su archivo de pruebas en `tests/Unit/Competicion/` y cobertura total.
- Reglas fijas: tamaños de grupo equilibrados y cabezas de serie en
  serpiente; círculo de Berger, con número fijo de partidos se toman las
  primeras jornadas y `k·n` impar lanza; enfrentamiento directo solo entre
  dos, mini-liga con tres o más; el sorteo de desempate se persiste y no
  cambia entre recálculos; mejores terceros con ajuste por grupos de
  distinto tamaño; byes a los mejores clasificados y cabezas 1 y 2 en
  mitades opuestas; `AvanceDeCuadro` es idempotente y `revertir` lanza si
  el siguiente ya se jugó; walkover cuenta 2-0 en sets y 6-0 por set
  (configurable en `Puntuacion`).

## Acciones que unen motor y modelos (`App\Actions\Competicion`)

- Cada acción abre una transacción en la base del organizador, construye
  los DTOs desde los modelos, llama al motor, persiste lo que devuelve y
  escribe en `registro_de_cambios` con el usuario, antes y después.
- Resortear o regenerar está bloqueado si la fase tiene algún partido
  jugado, salvo `forzar`, que anula los resultados.
- La clasificación se materializa en `clasificaciones` y se recalcula en
  el mismo request al guardar o anular un resultado.
- Un resultado registrado por un jugador queda `pendiente_validacion`
  hasta que el rival lo confirma o la organización lo valida.

## Datos de personas

- En público nunca aparecen teléfono ni email; solo nombre y apellidos, o
  un alias si el organizador activa la privacidad.
- Consentimiento explícito al registrar un jugador. Fecha de nacimiento
  opcional, con aviso de autorización de tutor si es menor de 14 años.
- Nada de datos reales en tests, fixtures, seeders ni documentación.

## Estilo

- Español en código de dominio, comentarios, textos, mensajes y commits.
- A jugadores y organizadores se les trata de usted.
- Los comentarios explican por qué, no qué.
- Textos cortos: se leen desde el móvil al lado de la pista.

## Antes de dar algo por terminado

1. `vendor/bin/pint --dirty --format agent`.
2. La prueba Pest del cambio: en el motor, los casos de la tabla de
   `docs/propuesta-fase-1.md`; en el resto, con el rol que puede y con
   uno que no.
3. `composer ci:check`.
4. Si se cierra una tarea de la fase, se marca en `docs/briefing.md` y se
   anota lo que cambió respecto a la propuesta.
