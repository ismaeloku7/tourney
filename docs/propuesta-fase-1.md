# Propuesta de fase 1: esquema de tablas y clases del motor

Propuesta para revisar antes de escribir código, como pide el
[briefing](briefing.md). Cubre las seis tareas de la fase 1: proyecto base,
base central, migraciones del tenant, DTOs, motor de competición y acciones
que unen motor y modelos. No hay interfaz.

Convenciones tomadas de `clubexploradoressaas` (CEAS) que se aplican tal
cual: tablas, columnas, clases y métodos en español; migraciones con un
comentario de cabecera que explica la tabla; `deleted_at` en las tablas del
tenant que guardan cosas (no en pivotes ni registros); enums en `App\Enums`
con `etiqueta()`; acciones en `App\Actions\<Área>\<Verbo>.php` con un
método `handle()`; dominio puro en carpetas de primer nivel de `app/`
(`App\Competicion`, como `App\Tesoreria` en CEAS); Pest 5 con
`RefreshDatabase` en la base central y una base por organizador que la
prueba crea y borra; Pint con preset `laravel`; PHPStan nivel 7 con
Larastan; CI de GitHub Actions con `composer ci:check`.

## Decisiones que tomo si no se dice lo contrario

| Decisión abierta | Propuesta | Por qué |
| --- | --- | --- |
| Nombre del producto | El repositorio se llama `tourney`; el nombre comercial queda en `APP_NAME` y el dominio central en `SAAS_DOMINIO`, como en CEAS. No se fija nada más en código | Cambiar un nombre de `.env` no cuesta nada; un namespace sí |
| Base de producción | MySQL 8, igual que la CI de CEAS. SQLite en local y tests | Los managers de stancl y la CI ya están probados ahí |
| Despliegue | VPS con Forge, DNS comodín `*.SAAS_DOMINIO` y certificado comodín. Dominios propios en fase 4 | Misma pauta que CEAS; Laravel Cloud no da `CREATE DATABASE` por organizador con garantías |
| Correo transaccional | Resend (transporte nativo de Laravel 13) | Sin paquete extra |
| Plan gratuito | Solo la marca de la plataforma en el pie, sin publicidad | Fuera de fase 1 en cualquier caso |
| Confirmación de pareja | Con confirmación del compañero, plazo de 48 horas | Lo que asume el briefing |
| Datos en público | Nunca teléfono ni email | Lo que asume el briefing |
| Dónde viven los datos de la organización | Identidad (slug, nombre, estado, plan, dominios) en la central; marca, zona horaria y textos legales en la tabla `ajustes` del tenant | La central se queda pequeña; el ZIP de baja se lleva todo lo del organizador; el manifiesto PWA ya necesita la base del tenant para el logo |
| Límites de plan | Se comprueban contando en la base del tenant en el mismo request (torneos activos, inscripciones confirmadas por categoría). La tabla central `eventos_uso` sirve para métricas, no para límites | Contar en el tenant es una consulta barata y siempre exacta; el panel de plataforma agrega `eventos_uso` sin abrir tenants |
| Variables de entorno | `SAAS_DOMINIO`, `ORGANIZADORES_HOSTS_DESARROLLO`, `ORGANIZADORES_PREFIJO_BASE` | Los nombres de CEAS con «organizador» en lugar de «club» |

## Base central

Las tablas de Laravel (`sessions`, `cache`, `jobs`, `job_batches`,
`failed_jobs`) viven aquí, como en CEAS. Las tablas propias:

### `organizadores` (modelo `Organizador`, el tenant de stancl)

| Columna | Tipo | Notas |
| --- | --- | --- |
| `id` | string(40) PK | El slug: subdominio y nombre de la base `organizador_{id}`. Sin autoincremento, como `Club` en CEAS |
| `nombre` | string(120) | |
| `estado` | string(20) índice | `EstadoOrganizador`: pendiente, activo, suspendido, baja |
| `plan_id` | FK `planes` | Plan actual; el histórico va en `suscripciones_plan` |
| `responsable_nombre` | string(120) | Se copia al usuario propietario al crear la base |
| `responsable_email` | string(190) | Idem |
| `responsable_password` | string nullable | Hash bcrypt. Se vacía al crear al propietario en el tenant |
| `verificacion_token` | char(64) nullable | Hash del token del correo de verificación |
| `verificacion_expira_en` | timestamp nullable | |
| `verificado_en` | timestamp nullable | |
| `suspendido_en` | timestamp nullable | |
| `suspension_motivo` | string nullable | |
| `baja_programada_en` | timestamp nullable | Borrado de la base 30 días después |
| `exportacion_path` | string nullable | ZIP de datos generado antes de la baja |
| `ultimo_acceso` | timestamp nullable | Lo anota el panel del organizador |
| `data` | json nullable | Columna libre de stancl |
| `timestamps` | | |

### `domains` (modelo de stancl, con columnas extra)

`id`, `domain` único, `tenant_id` FK `organizadores` (cascade), más
`verificacion_token` string nullable, `verificado_en` timestamp nullable y
`certificado_estado` string(20) nullable (pendiente, activo, error). Un
subdominio se guarda sin el dominio central (`miclub`); un dominio propio,
entero y normalizado (`torneos.miclub.es`), como en CEAS.

### `planes` (modelo `Plan`)

`id`, `clave` string(30) única (gratis, club, organizador), `nombre`,
`precio_mensual` decimal(8,2) nullable (informativo), `torneos_activos`
smallint nullable, `parejas_por_torneo` smallint nullable,
`categorias_por_torneo` smallint nullable (null = ilimitado en los tres),
`sin_marca` bool, `push_ilimitado` bool, `exportaciones` bool,
`dominio_propio` bool, `varios_administradores` bool, `orden` smallint,
`activo` bool, timestamps. Se siembran los tres del briefing.

### `suscripciones_plan` (modelo `SuscripcionPlan`)

`id`, `organizador_id` FK, `plan_id` FK, `desde` date, `hasta` date
nullable, `notas` text nullable, `cambiado_por` FK nullable
`usuarios_plataforma`, timestamps. Cambiar de plan cierra la fila abierta y
abre otra; `organizadores.plan_id` es la copia de la fila abierta.

### `usuarios_plataforma` (modelo `UsuarioPlataforma`, guard `plataforma`)

Como `administradores` en CEAS: `id`, `nombre`, `email` único, `password`,
`remember_token`, `ultimo_acceso`, timestamps. Tabla
`password_reset_tokens_plataforma` para su broker.

### `eventos_uso` (modelo `EventoUso`)

`id`, `organizador_id` FK (cascade), `tipo` string(40) índice
(`TipoDeEventoDeUso`: organizador_verificado, torneo_publicado,
inscripcion_confirmada, push_enviado, correo_enviado), `cantidad` int
(1 por defecto; los push agrupados anotan cuántos), `referencia` string
nullable (id del torneo en el tenant), `ocurrido_en` timestamp. Índice
`(organizador_id, tipo, ocurrido_en)`. Se escribe desde el tenant con
`tenancy()->central(fn () => …)`.

### `auditoria_global` (modelo `AuditoriaGlobal`)

`id`, `usuario_plataforma_id` FK nullable, `organizador_id` FK nullable,
`accion` string(60) (activar, suspender, cambiar_plan, entrar_como,
programar_baja, verificar_dominio…), `detalles` json nullable,
`created_at` índice. Sin `updated_at`: no se edita.

## Base del organizador (tenant)

Nombre de la base: `organizador_{slug}` (`.sqlite` en local). Todas las
tablas marcadas con 🗑 llevan `deleted_at`. Las columnas de estado son
`string` cortos con un enum PHP detrás, como en CEAS. Fase 1 crea todas
las tablas de esta sección salvo las marcadas «fase 2» o «fase 3», que se
añaden con su migración cuando toquen.

### Organización y equipo

**`ajustes`** · clave string(40) PK, `valor` json nullable, `updated_at`.
Claves del enum `Ajuste`: nombre_publico, logo_path, color_primario,
color_secundario, zona_horaria (`Europe/Madrid` por defecto),
texto_legal_aviso, texto_legal_privacidad, texto_legal_condiciones,
privacidad_alias_por_defecto, contacto_email, contacto_telefono.

**`users`** 🗑 · como el `users` de CEAS: `id`, `nombre`, `apellidos`,
`email` único, `telefono` nullable, `rol` string(20) índice
(`Rol`: propietario, administrador, arbitro), `activo` bool,
`email_verified_at`, `password`, `remember_token`, `ultimo_acceso`,
timestamps. Con `password_reset_tokens`. Un invitado se crea sin
contraseña y recibe un enlace firmado para ponerla, como en CEAS.

**`torneo_administradores`** · `torneo_id` FK, `user_id` FK, timestamps;
único `(torneo_id, user_id)`. El pivote `torneo_administrador` del
briefing con el nombre en plural de las tablas de CEAS.

**`torneo_arbitros`** · `torneo_id` FK, `user_id` FK, `pista_id` FK nullable
(null = todas las pistas del torneo), timestamps; único
`(torneo_id, user_id, pista_id)`.

### Instalaciones y formatos

**`sedes`** 🗑 · `id`, `nombre` string(120), `direccion` string(255)
nullable, `mapa_url` string(500) nullable, `orden` smallint, timestamps.

**`pistas`** 🗑 · `id`, `sede_id` FK nullable (null on delete), `nombre`
string(60), `orden` smallint, `activa` bool, timestamps.

**`formatos_partido`** 🗑 (modelo `FormatoPartido`) · `id`, `nombre`
string(120), `sets` tinyint (1, 3, 5), `juegos_por_set` tinyint (4, 6, 9),
`diferencia_para_ganar_set` tinyint (1, 2), `tie_break_en` tinyint
nullable (juegos iguales a los que se juega: 6, 4 u 8; null = sin
tie-break), `tie_break_puntos` tinyint (7), `super_tie_break_final` bool,
`super_tie_break_puntos` tinyint (10), `punto_de_oro` bool,
`por_tiempo_minutos` smallint nullable, `duracion_estimada` smallint
(minutos), `es_predeterminado` bool, timestamps. El seeder base del tenant
crea «Al mejor de 3 sets a 6», «Un set a 6 con tie-break», «Un set a 9»
y «Al mejor de 3 con super tie-break».

### Torneo y categorías

**`torneos`** 🗑 · `id`, `nombre` string(160), `slug` string(80) único
(para `/torneos/{slug}`), `deporte` string(20) (`padel`), `sede_id` FK
nullable, `direccion` string nullable, `fecha_inicio` date, `fecha_fin`
date, `inscripcion_desde` datetime nullable, `inscripcion_hasta` datetime
nullable, `estado` string(24) índice (`EstadoTorneo`: borrador,
inscripcion_abierta, inscripcion_cerrada, sorteo, en_juego, finalizado,
archivado), `listado_en_plataforma` bool, `resultados_por_jugadores`
string(16) (`ResultadosPorJugadores`: desactivado, con_validacion,
sin_validacion), `minutos_para_validar` smallint (30), `privacidad_alias`
bool, `cartel_path` string nullable, `normativa` text nullable, `contacto`
string nullable, `descanso_minimo_minutos` smallint (30),
`margen_entre_partidos_minutos` tinyint (0, 5, 10), `campos_extra` json
nullable (definición de campos de inscripción, fase 3), `publicado_en`,
`horarios_publicados_en`, `finalizado_en`, `archivado_en` timestamps
nullables, `creado_por` FK nullable `users`, timestamps. Índices en
`(estado, fecha_inicio)`.

**`torneo_pistas`** · `id`, `torneo_id` FK, `pista_id` FK, `orden`
smallint, `disponibilidad` json (lista de `{fecha, desde, hasta}` validada
por el DTO `DisponibilidadDePista`), timestamps; único
`(torneo_id, pista_id)`.

**`franjas`** · `id`, `torneo_id` FK, `pista_id` FK, `inicio` datetime,
`fin` datetime, `bloqueada` bool (el organizador la reserva a mano),
timestamps; único `(pista_id, inicio)`, índice `(torneo_id, inicio)`. Se
generan desde `torneo_pistas.disponibilidad` con la duración del formato
y el margen. Un partido referencia su franja con FK única: una franja
aloja como máximo un partido.

**`categorias`** 🗑 · `id`, `torneo_id` FK, `nombre` string(80),
`modalidad` string(12) (`Modalidad`: individual, parejas, equipos; v1
solo parejas), `genero` string(12) (`Genero`: masculino, femenino, mixto,
abierto), `nivel` string(40) nullable, `cupo` smallint nullable,
`minimo_participantes` smallint (0), `precio` decimal(8,2) nullable,
`formato_partido_id` FK nullable, `orden` smallint (también es la
prioridad del planificador), timestamps.

### Jugadores, participantes e inscripciones

**`jugadores`** 🗑 (guard `jugador`) · `id`, `nombre` string(80),
`apellidos` string(120), `email` string(190) único nullable, `telefono`
string(30) nullable, `fecha_nacimiento` date nullable, `genero` string(12)
nullable, `nivel_declarado` string(40) nullable, `alias` string(60)
nullable, `password` string nullable, `remember_token`,
`email_verified_at`, `consentimiento_en` timestamp nullable,
`avisos` json nullable (qué avisos quiere), `identificador_plataforma`
string nullable (reservado para la cuenta global de fase 2),
`anonimizado_en` timestamp nullable (derecho de supresión: se vacían
nombre, email y teléfono; sus participantes siguen), `fusionado_en_id` FK
nullable `jugadores` (duplicado fusionado en otro), `ultimo_acceso`,
timestamps.

**`enlaces_magicos`** · `id`, `jugador_id` FK (cascade), `token` char(64)
único (hash), `expira_en` timestamp, `usado_en` timestamp nullable,
`created_at`. Un solo uso, 15 minutos. Fase 3; la tabla se crea entonces.

**`participantes`** 🗑 (modelo `Participante`, «Pareja» en pádel) · `id`,
`categoria_id` FK, `nombre` string(160) (visible: «García / López»),
`cabeza_de_serie` smallint nullable (1 = primera), `estado` string(12)
(`EstadoParticipante`: activo, retirado), timestamps.

**`participante_jugadores`** · `id`, `participante_id` FK (cascade),
`jugador_id` FK, `orden` tinyint (1 o 2), `confirmado_en` timestamp
nullable (null mientras el compañero invitado no confirma),
`invitacion_token` char(64) nullable, `invitacion_expira_en` timestamp
nullable, timestamps; único `(participante_id, jugador_id)`, índice
`jugador_id`. En pádel siempre dos filas; con «equipos» serían más.

**`inscripciones`** 🗑 · `id`, `categoria_id` FK, `participante_id` FK
único, `estado` string(16) índice (`EstadoInscripcion`: pendiente,
confirmada, lista_de_espera, baja), `posicion_espera` smallint nullable,
`pago_marcado_en` timestamp nullable, `pago_notas` string nullable,
`notas` text nullable, `origen` string(12) (`OrigenInscripcion`: online,
manual, importacion), `respuestas_extra` json nullable, `confirmada_en`,
`baja_en` timestamps nullables, `registrada_por` FK nullable `users`,
timestamps. `categoria_id` se repite respecto a `participantes` porque es
la tabla que filtra el panel; la acción que inscribe garantiza que
coinciden.

**`disponibilidades`** · `id`, `jugador_id` FK (cascade), `torneo_id` FK
(cascade), `desde` datetime, `hasta` datetime, timestamps; índice
`(torneo_id, jugador_id)`. Intervalos en los que el jugador **no** puede.

### Competición

**`fases`** 🗑 · `id`, `categoria_id` FK, `tipo` string(16)
(`TipoDeFase`: grupos, eliminatoria, consolacion, liga), `nombre`
string(80), `orden` smallint, `configuracion` json (DTO según el tipo, ver
más abajo), `formato_partido_id` FK nullable (sobrescribe el de la
categoría), `estado` string(12) (`EstadoFase`: pendiente, preparada,
en_juego, cerrada), `fase_origen_id` FK nullable `fases` (de qué fase
salen sus participantes: la consolación de la de grupos o del cuadro),
`semilla_sorteo` int nullable (para repetir el sorteo), `cerrada_en`
timestamp nullable, timestamps; único `(categoria_id, orden)`.

**`grupos`** · `id`, `fase_id` FK (cascade), `nombre` string(20) («A»),
`orden` smallint, timestamps.

**`grupo_participantes`** · `grupo_id` FK (cascade), `participante_id` FK,
`posicion_sorteo` smallint; único `(grupo_id, participante_id)`. Que un
participante esté en un solo grupo por fase lo garantiza la acción de
sorteo (no hay `fase_id` aquí para no duplicarlo).

**`partidos`** 🗑 · `id`, `fase_id` FK, `grupo_id` FK nullable, `jornada`
smallint nullable (orden en el round robin), `ronda` smallint nullable
(1 = primera ronda del cuadro; la final es la última), `posicion`
smallint nullable (hueco dentro de la ronda, desde 1), `es_tercer_puesto`
bool, `local_id` FK nullable `participantes`, `visitante_id` FK nullable
`participantes`, `origen_local` json nullable, `origen_visitante` json
nullable (DTO `Origen`: `{tipo: ganador_de|perdedor_de|posicion_de_grupo|clasificado, partido_id|grupo_id, posicion}`),
`formato_partido_id` FK nullable (formato propio de la ronda),
`pista_id` FK nullable, `franja_id` FK nullable **único**, `estado`
string(24) índice (`EstadoPartido`: pendiente, programado, en_juego,
pendiente_validacion, jugado, walkover, anulado), `especial` string(16)
nullable (`ResultadoEspecial`: walkover, retirada, no_presentado),
`ganador_id` FK nullable `participantes`, `registrado_por_jugador_id` FK
nullable, `registrado_en`, `validacion_vence_en`, `validado_en`
timestamps nullables, `validado_por` FK nullable `users`, `notas` string
nullable, timestamps; único `(fase_id, ronda, posicion)`, índices
`(fase_id, estado)`, `(pista_id, franja_id)`.

**`sets`** · `id`, `partido_id` FK (cascade), `numero` tinyint,
`juegos_local` tinyint, `juegos_visitante` tinyint, `tie_break_local`
tinyint nullable, `tie_break_visitante` tinyint nullable,
`es_super_tie_break` bool; único `(partido_id, numero)`.

**`clasificaciones`** · `id`, `fase_id` FK (cascade), `grupo_id` FK
nullable (cascade), `participante_id` FK, `posicion` smallint, `puntos`
smallint, `pj`, `pg`, `pp` smallint, `sets_favor`, `sets_contra`,
`juegos_favor`, `juegos_contra` smallint, `desempate_aplicado` string(40)
nullable, `sorteo_desempate` int nullable (número fijado la primera vez
que hizo falta, para que el recálculo no lo cambie), `clasificado_como`
string(40) nullable («1º A», «mejor tercero»), `calculada_en` timestamp;
único `(fase_id, grupo_id, participante_id)`.

### Registro, push y comunicaciones

**`registro_de_cambios`** · como en CEAS: `id`, `user_id` FK nullable,
`jugador_id` FK nullable, `accion` string(40), `sujeto_type`, `sujeto_id`
(morph), `antes` json nullable, `despues` json nullable, `created_at`
índice. Toda operación de sorteo, cuadro, horario y resultado escribe
aquí dentro de su transacción.

**`push_suscripciones`**, **`push_envios`** · copiadas de CEAS, con
`user_id` nullable y `jugador_id` nullable en lugar de `email_familia`,
más `sigue_organizador` bool. **`push_suscripcion_torneos`** ·
`suscripcion_id` FK, `torneo_id` FK, único. Fase 3.

**`notifications`** · la tabla estándar de Laravel para el historial del
portal del jugador. Fase 3.

**`comunicaciones`** · `id`, `torneo_id` FK, `categoria_id` FK nullable,
`titulo`, `cuerpo` text, `canales` json, `enviada_por` FK `users`,
`enviada_en`, `destinatarios` int, timestamps. Fase 3.

**`plantillas_fase`** · `id`, `nombre`, `tipo`, `configuracion` json,
timestamps. Fase 2.

## Enums (`App\Enums`)

`EstadoOrganizador`, `TipoDeEventoDeUso`, `Ajuste`, `Rol`, `EstadoTorneo`,
`ResultadosPorJugadores`, `Modalidad`, `Genero`, `EstadoParticipante`,
`EstadoInscripcion`, `OrigenInscripcion`, `TipoDeFase`, `EstadoFase`,
`EstadoPartido`, `ResultadoEspecial`, `ModoDeSorteo` (aleatorio,
cabezas_de_serie, manual), `CriterioDeDesempate` (puntos,
enfrentamiento_directo, diferencia_de_sets, sets_ganados,
diferencia_de_juegos, juegos_ganados, sorteo), `ReglaDeCruces`
(entre_grupos, sorteo, manual), `OrigenDeConsolacion` (ninguna,
no_clasificados, perdedores_primera_ronda), `TipoDeOrigen` (ganador_de,
perdedor_de, posicion_de_grupo, clasificado). Todos `string` con
`etiqueta()`; `EstadoTorneo` lleva además `siguiente()` y `anterior()`.

## Motor de competición (`App\Competicion`)

Clases `final readonly` sin Eloquent, sin `now()`, sin `config()`. Los ids
de participante son `int` opacos. Todo lo aleatorio recibe una semilla y
usa `mt_srand($semilla)` localmente, de modo que el mismo sorteo se
repite. Los servicios lanzan excepciones de `App\Competicion\Errores` solo
ante entradas imposibles; lo que es una decisión del organizador (grupos
desiguales, conflictos de horario) vuelve como aviso en el DTO de salida.

### DTOs (`App\Competicion\Datos`)

| Clase | Campos |
| --- | --- |
| `Participante` | `id`, `nombre`, `cabezaDeSerie ?int`, `jugadores list<int>` (para que el planificador sepa quién juega en dos categorías) |
| `FormatoDePartido` | `sets`, `juegosPorSet`, `diferenciaParaGanarSet`, `tieBreakEn ?int`, `tieBreakPuntos`, `superTieBreakFinal bool`, `superTieBreakPuntos`, `porTiempoMinutos ?int`, `duracionEstimada`. Constructor con los valores por defecto del briefing y `setsParaGanar()` |
| `Puntuacion` | `victoria`, `derrota`, `walkoverFavor`, `walkoverContra`, `noJugado`, `porSetGanado ?int` (2, 0, 2, 0, 0, null) |
| `ConfiguracionDeGrupos` | `numeroDeGrupos ?int` o `tamanoDeGrupo ?int` (uno de los dos), `modoDeSorteo`, `semilla ?int`, `partidosPorParticipante ?int` (null = todos contra todos), `idaYVuelta bool`, `puntuacion`, `criteriosDeDesempate list<CriterioDeDesempate>`, `clasificados ReglaDeClasificados`, `gruposManuales ?array` |
| `ReglaDeClasificados` | `porGrupo int` (N primeros), `mejoresDePosicion ?int`, `cuantosMejores int` |
| `ConfiguracionDeEliminatoria` | `tamanoDeCuadro ?int` (null = el mínimo que cabe), `reglaDeCruces`, `evitarReencuentros bool`, `tercerYCuartoPuesto bool`, `consolacion OrigenDeConsolacion`, `formatosPorRonda array<int, int>` (ronda → id de formato), `crucesManuales ?array`, `semilla ?int` |
| `Set` | `juegosLocal`, `juegosVisitante`, `tieBreakLocal ?int`, `tieBreakVisitante ?int` |
| `Resultado` | `sets list<Set>`, `especial ?ResultadoEspecial`, `ganaLocal ?bool` (obligatorio con especial o por tiempo) |
| `Partido` | `id ?int`, `grupo ?int`, `jornada ?int`, `ronda ?int`, `posicion ?int`, `esTercerPuesto`, `local ?int`, `visitante ?int`, `origenLocal ?Origen`, `origenVisitante ?Origen`, `estado EstadoPartido`, `ganador ?int`, `resultado ?Resultado`. Métodos `conGanador()`, `vacio()` que devuelven copias |
| `Origen` | `tipo TipoDeOrigen`, `referencia int` (id de partido o de grupo), `posicion ?int`. `etiqueta()` da «Ganador P3» o «1º grupo A» |
| `Grupo` | `nombre`, `orden`, `participantes list<int>` en orden de sorteo |
| `RepartoDeGrupos` | `grupos list<Grupo>`, `avisos list<string>` («quedan grupos de 4 y de 5») |
| `LineaDeClasificacion` | `participante`, `posicion`, `puntos`, `pj`, `pg`, `pp`, `setsFavor`, `setsContra`, `juegosFavor`, `juegosContra`, `criterioAplicado ?CriterioDeDesempate`, `sorteo ?int` |
| `Clasificacion` | `grupo ?int`, `lineas list<LineaDeClasificacion>`, `completa bool` (todos los partidos del grupo cerrados) |
| `Clasificado` | `participante`, `grupo`, `posicionEnGrupo`, `origen` («1º A», «mejor 3º»), `orden` (1 = mejor clasificado global) |
| `Cuadro` | `tamano`, `rondas int`, `partidos list<Partido>`, `avisos` |
| `ValidacionDeResultado` | `valido bool`, `errores list<string>`, `ganaLocal ?bool`, `setsLocal`, `setsVisitante` |
| `FranjaDisponible` | `id`, `pista`, `inicio DateTimeImmutable`, `fin` |
| `PartidoAPlanificar` | `id`, `categoria`, `prioridadDeCategoria`, `ronda`, `jornada`, `local ?int`, `visitante ?int`, `jugadores list<int>`, `duracionMinutos`, `dependeDe list<int>` (partidos que deben jugarse antes) |
| `RestriccionesDePlanificacion` | `descansoMinimoMinutos`, `noDisponibilidades array<int jugador, list<Intervalo>>`, `partidosYaFijados array<int partido, FranjaDisponible>` |
| `Asignacion` | `partido`, `franja` |
| `Conflicto` | `partido`, `tipo` (sin_franja, jugador_solapado, sin_descanso, jugador_no_disponible, orden_de_rondas), `detalle` |
| `Planificacion` | `asignaciones list<Asignacion>`, `conflictos list<Conflicto>` |

Los DTOs de configuración (`ConfiguracionDeGrupos`,
`ConfiguracionDeEliminatoria`, `FormatoDePartido`,
`DisponibilidadDePista`) tienen `desdeArray(array)` que valida y lanza
`ConfiguracionInvalida` con los mensajes, y `toArray()`. Son lo que se
guarda en `fases.configuracion` y lo que el formulario de Filament
rellenará en fase 2.

### Servicios

| Clase | Firma | Reglas |
| --- | --- | --- |
| `ValidadorDeResultado` | `validar(Resultado $resultado, FormatoDePartido $formato): ValidacionDeResultado` | Cada set cumple juegos por set y diferencia; tie-break solo en `tieBreakEn` iguales y con sus puntos; super tie-break solo en el set decisivo si está activo; el partido acaba cuando un lado llega a `setsParaGanar()` y no hay sets de más; con `especial` o `porTiempoMinutos` exige `ganaLocal`; devuelve el ganador |
| `RepartidorDeGrupos` | `repartir(list<Participante> $participantes, ConfiguracionDeGrupos $configuracion): RepartoDeGrupos` | Calcula tamaños equilibrados (`n` en `g` grupos: los primeros `n mod g` grupos con uno más); cabezas de serie en serpiente (1→A, 2→B, …, g+1→última letra); el resto aleatorio con semilla; modo manual respeta `gruposManuales` y solo valida; avisa si hay grupos de distinto tamaño o menos de 3 |
| `GeneradorRoundRobin` | `generar(Grupo $grupo, ?int $partidosPorParticipante, bool $idaYVuelta): list<Partido>` | Círculo de Berger con comodín para grupos impares (el que descansa se anota en la jornada); con número fijo `k < n-1` toma las `k` primeras jornadas del círculo (cada jornada da un partido a cada participante, así la carga es igual) y lanza `NumeroDePartidosNoAlcanzable` si `k·n` es impar o `k ≥ n`; ida y vuelta duplica las jornadas invirtiendo local y visitante |
| `CalculadorDeClasificacion` | `calcular(list<int> $participantes, list<Partido> $partidos, Puntuacion $puntuacion, list<CriterioDeDesempate> $criterios, array<int,int> $sorteosPrevios = []): Clasificacion` | Suma puntos, sets y juegos de los partidos cerrados (walkover suma el resultado estándar 2-0 en sets y 6-0 por set a favor del ganador, configurable); ordena por los criterios en orden; en un empate de dos, `enfrentamiento_directo` mira su partido; con tres o más, la mini-liga se calcula con `calcular()` recursivo sobre los partidos entre las empatadas y se sigue al siguiente criterio solo para las que siguen empatadas; `sorteo` usa `sorteosPrevios[participante]` si existe y si no genera uno y lo devuelve en la línea para que la aplicación lo persista; `criterioAplicado` dice qué criterio separó cada línea de la siguiente |
| `SelectorDeClasificados` | `seleccionar(list<Clasificacion> $clasificaciones, ReglaDeClasificados $regla, list<CriterioDeDesempate> $criterios, Puntuacion $puntuacion, list<Partido> $partidos): list<Clasificado>` | Los `porGrupo` primeros de cada grupo; después los `cuantosMejores` de la posición `mejoresDePosicion` comparados entre sí con los mismos criterios; si los grupos tienen distinto tamaño recalcula la línea de cada candidato descartando sus partidos contra el último de su grupo cuando el grupo es de los grandes; el `orden` global sale de posición en grupo primero y de la comparación después (todos los primeros, luego los segundos…) |
| `ConstructorDeCuadro` | `construir(list<Clasificado> $clasificados, ConfiguracionDeEliminatoria $configuracion): Cuadro` | Tamaño = potencia de 2 ≥ clasificados si no se fija; byes a los de mejor `orden`; colocación de cabezas por el esquema estándar (1 y 2 en extremos opuestos, 3 y 4 en los cuartos restantes, y así); con `entre_grupos` empareja 1º de un grupo con 2º de otro; `evitarReencuentros` prueba permutaciones de los huecos de la misma categoría de cabeza hasta que ningún cruce de primera ronda repite grupo, y avisa si no es posible; `sorteo` baraja con semilla; `manual` toma `crucesManuales` y valida; genera las rondas siguientes vacías con `origenLocal`/`origenVisitante` de tipo `ganador_de`, y el partido por el tercer puesto con `perdedor_de` las semifinales |
| `ConstructorDeConsolacion` | `construir(list<Clasificado> $noClasificados, ConfiguracionDeEliminatoria $configuracion): Cuadro` y `desdePerdedores(Cuadro $principal, ConfiguracionDeEliminatoria $configuracion): Cuadro` | Reutiliza `ConstructorDeCuadro`; en `desdePerdedores` los huecos de primera ronda llevan `perdedor_de` los partidos de la primera ronda del principal, en el mismo orden |
| `AvanceDeCuadro` | `avanzar(Cuadro $cuadro, Partido $cerrado): Cuadro` y `revertir(Cuadro $cuadro, Partido $anulado): Cuadro` | Busca los huecos cuyo origen apunta a `cerrado` (ganador y, si hay tercer puesto o consolación, perdedor) y los rellena; si ya estaban rellenos con el mismo participante no hace nada (idempotente); `revertir` vacía esos huecos y lanza `AvanceNoReversible` si el partido siguiente ya tiene resultado; si el rival de un hueco es un bye, el partido siguiente se cierra solo con estado `walkover` sin especial |
| `Planificador` | `planificar(list<PartidoAPlanificar> $partidos, list<FranjaDisponible> $franjas, RestriccionesDePlanificacion $restricciones): Planificacion` | Voraz: ordena partidos por prioridad de categoría, ronda, jornada; para cada uno recorre las franjas libres por orden de inicio y toma la primera que cabe (duración ≤ franja), sin solapar a ningún jugador en otra franja ya asignada, respetando el descanso mínimo y las no disponibilidades, y después de las franjas de los partidos de los que depende; si no hay, lo deja sin asignar con un `Conflicto` que explica el motivo más cercano; nunca lanza. `partidosYaFijados` se respetan y se usan como ocupadas |

### Errores (`App\Competicion\Errores`)

`ConfiguracionInvalida`, `NumeroDePartidosNoAlcanzable`,
`CuadroDemasiadoPequeno`, `AvanceNoReversible`, `ParticipanteDuplicado`.
Todas extienden `ErrorDeCompeticion` (`DomainException`).

## Acciones de aplicación (`App\Actions\Competicion`)

Unen motor y modelos. Cada una abre una transacción en la base del
tenant, llama al motor con DTOs construidos desde los modelos, persiste
lo que devuelve y escribe en `registro_de_cambios` con el usuario, antes y
después.

| Acción | `handle(...)` | Qué hace |
| --- | --- | --- |
| `SortearGrupos` | `(Fase $fase, User $usuario, bool $forzar = false)` | Exige fase de grupos sin partidos jugados (o `forzar`, que anula resultados); borra grupos anteriores; `RepartidorDeGrupos`; guarda `grupos`, `grupo_participantes`, `semilla_sorteo`; fase pasa a `preparada` |
| `GenerarPartidos` | `(Fase $fase, User $usuario)` | Por cada grupo, `GeneradorRoundRobin`; crea `partidos` en estado `pendiente` con `jornada`; crea las filas de `clasificaciones` a cero |
| `PlanificarHorarios` | `(Torneo $torneo, User $usuario)` | Construye `PartidoAPlanificar` de todos los partidos pendientes del torneo (todas las categorías), `FranjaDisponible` de las franjas libres, restricciones desde `disponibilidades` y el torneo; `Planificador`; guarda `franja_id`, `pista_id` y estado `programado`; devuelve los conflictos |
| `GenerarFranjas` | `(Torneo $torneo)` | Desde `torneo_pistas.disponibilidad`, la duración del formato y el margen; no toca franjas ocupadas |
| `RegistrarResultado` | `(Partido $partido, Resultado $resultado, User|Jugador $quien)` | `ValidadorDeResultado`; guarda sets, ganador y estado (`jugado`, `walkover`, o `pendiente_validacion` si lo registra un jugador y el torneo exige validación); recalcula la clasificación del grupo con `CalculadorDeClasificacion` persistiendo los sorteos de desempate; en eliminatoria llama a `AvanzarCuadro` |
| `ValidarResultado` | `(Partido $partido, Jugador|User $quien)` | De `pendiente_validacion` a `jugado` y lo mismo que arriba |
| `AnularResultado` | `(Partido $partido, User $usuario)` | Vuelve a `programado` o `pendiente`, borra sets, recalcula la clasificación; en eliminatoria `AvanceDeCuadro::revertir` y lanza si no se puede |
| `CerrarFase` | `(Fase $fase, User $usuario)` | Exige todos los partidos cerrados; `SelectorDeClasificados`; marca `clasificado_como`; si hay fase siguiente de eliminatoria, `ConstructorDeCuadro` (y `ConstructorDeConsolacion` si toca) y crea sus partidos; fase a `cerrada`, la siguiente a `preparada` |
| `AvanzarCuadro` | `(Partido $cerrado)` | Interno: `AvanceDeCuadro::avanzar` y persistir los huecos cambiados |
| `CambiarEstadoDelTorneo` | `(Torneo $torneo, EstadoTorneo $estado, User $usuario)` | Las transiciones del diagrama, con las comprobaciones de cada una (no se publica sin categorías, no se pasa a en juego sin horarios) |

Acciones de plataforma (`App\Actions\Plataforma`), fase 1 solo las de
tenant: `CrearOrganizador` (fila central, base, migraciones, seeder base
del tenant, propietario), `DestruirOrganizador`. Comandos
`organizadores:crear {slug} {nombre} {email}` y
`organizadores:destruir {slug} --confirmar`.

## Modelos (`App\Models`)

Central: `Organizador` (extiende `Tenant` de stancl, como `Club`), `Plan`,
`SuscripcionPlan`, `UsuarioPlataforma`, `EventoUso`, `AuditoriaGlobal`.
Tenant: `User`, `Jugador`, `Sede`, `Pista`, `FormatoPartido`, `Torneo`,
`TorneoPista`, `Franja`, `Categoria`, `Participante`, `Inscripcion`,
`Disponibilidad`, `Fase`, `Grupo`, `Partido`, `Set`, `Clasificacion`,
`RegistroDeCambio`. Los que tienen `deleted_at` usan `VaALaPapelera` y
`ReglasDePapelera` copiados de CEAS. `Fase` tiene un accessor
`configuracion()` que devuelve el DTO según `tipo`; `Partido` tiene
`aDto()` y `FormatoPartido` tiene `aDto()`.

Factories para todos los modelos del tenant y para `Organizador`, `Plan`
y `UsuarioPlataforma`. Estados útiles: `Torneo::factory()->enJuego()`,
`Partido::factory()->jugado()`, `Inscripcion::factory()->enEspera()`,
`Participante::factory()->conCabezaDeSerie(1)`.

## Seeder de pruebas

`DatabaseSeeder`: tres planes, un usuario de plataforma
(`admin@example.com`) y el organizador `demo` («Club Demo») en plan Club.
Dentro del tenant, `OrganizadorDePruebasSeeder` llama a:

- `EquipoSeeder`: `propietario@example.com`, `administrador@example.com`,
  `arbitro@example.com`, contraseña `password`, como en CEAS.
- `InstalacionesSeeder`: una sede, cuatro pistas, los formatos de partido
  por defecto.
- `JugadoresSeeder`: 48 jugadores, uno de ellos `jugador@example.com`,
  dos inscritos en dos categorías.
- `TorneosSeeder`: un torneo por estado. El de `sorteo` tiene dos
  categorías con 16 parejas en grupos de 4 y 2 partidos por pareja (el
  caso de la puerta de fase 2); el de `en_juego` tiene la mitad de los
  resultados de grupos metidos y un triple empate preparado; el de
  `finalizado` tiene cuadro de 8 con dos mejores terceros y consolación
  completos.

Se puede volver a sembrar sin duplicar (`firstOrCreate` por slug y email).

## Tests

`tests/Unit/Competicion/` (sin Laravel, un archivo por servicio), con los
casos mínimos del briefing y los que hacen falta para la cobertura total:

| Servicio | Casos |
| --- | --- |
| `ValidadorDeResultado` | 6-3 6-4 válido; 7-3 en set a 6 inválido; 7-6 exige tie-break; 7-5 sin tie-break; super tie-break en el tercero; set a 4 y set a 9; tercer set de más; walkover con ganador; por tiempo con empate a juegos sin ganador |
| `RepartidorDeGrupos` | 12 en 3 grupos; 13 en tamaño 4 (avisa 4-4-5); 8 cabezas en 4 grupos en serpiente; misma semilla, mismo reparto; manual con participante repetido lanza |
| `GeneradorRoundRobin` | 3, 4 y 5 todos contra todos (número de partidos, nadie repite, descansos marcados); 4 con 2 por pareja (4 partidos, cada uno juega 2); 4 con 3 por pareja es todos contra todos; 5 con 2 (5 partidos); 4 con 1 impar·impar lanza; ida y vuelta duplica e invierte |
| `CalculadorDeClasificacion` | tabla con puntos y sets; empate a dos por enfrentamiento directo; triple empate por diferencia de sets; triple empate con mini-liga antes del siguiente criterio; sorteo estable entre recálculos; walkover suma como victoria; `completa` solo con todos cerrados; puntos por set ganado |
| `SelectorDeClasificados` | 2 por grupo de 4 grupos; mejores terceros con grupos de 4 y 5 (descarta el partido contra el último del de 5); mejores segundos; orden global |
| `ConstructorDeCuadro` | 8 exactos; 12 en 16 con 4 byes a los 4 mejores; cabezas 1 y 2 en mitades opuestas; entre grupos sin reencuentros; sorteo con semilla; tercer puesto; manual inválido lanza; formatos por ronda |
| `ConstructorDeConsolacion` | perdedores de primera ronda con sus orígenes; no clasificados con 6 parejas en cuadro de 8 |
| `AvanceDeCuadro` | avanza ganador y perdedor; avanzar dos veces no cambia nada; bye cierra el siguiente solo; revertir vacía el hueco; revertir con el siguiente jugado lanza |
| `Planificador` | 20 partidos en 4 pistas sin solapar jugadores; un jugador en dos categorías no se solapa; descanso mínimo; no disponibilidad; ronda 2 después de ronda 1; sin franjas suficientes devuelve conflictos y no lanza; partidos ya fijados se respetan |

`tests/Feature/` con la base por organizador: `Tenancy` (crear y destruir
por comando; el host de `a` no ve la base de `b`), `Competicion` (cada
acción sobre el seeder: sortear, generar, planificar los 20 partidos en 4
pistas, registrar un resultado recalcula la tabla, cerrar fase rellena el
cuadro de 8 con mejores terceros y consolación, anular un resultado ya
avanzado revierte), `Seeders` (se siembra dos veces sin duplicar), y un
test que cuenta consultas al cargar un torneo con 200 parejas y 400
partidos.

## Orden de commits

1. Proyecto base: starter Livewire, Filament 5, stancl/tenancy, Pest,
   Pint, PHPStan nivel 7, `lang/es`, `CLAUDE.md` y `.ai/` adaptados de
   CEAS, CI, README.
2. Base central: migraciones, `Organizador`, `Plan`, `UsuarioPlataforma`,
   `EventoUso`, `AuditoriaGlobal`, `config/tenancy.php`, middleware
   `ResolverOrganizadorPorHost`, comandos crear y destruir, tests de
   tenancy.
3. Migraciones del tenant con modelos, factories y papelera.
4. Enums y DTOs con validación y sus tests.
5. Un commit por servicio del motor, en el orden de la tabla, cada uno
   con su test en verde.
6. Acciones de aplicación y seeder de pruebas con los tests de feature.

Cada commit pasa `vendor/bin/pint`, `vendor/bin/phpstan` y
`php artisan test` antes del push.

## Qué conviene que revises

1. Si la marca y los textos legales van en el tenant (`ajustes`) y no en
   la central, como propongo.
2. Si los límites de plan se cuentan en el tenant y `eventos_uso` queda
   solo para métricas.
3. El reparto con número fijo de partidos: tomar las `k` primeras jornadas
   del círculo es simple y equilibrado, pero con `k·n` impar es imposible
   y el sistema avisa en lugar de inventar un reparto desigual.
4. El walkover como 2-0 en sets y 6-0 por set a efectos de clasificación,
   configurable en `Puntuacion`.
5. Dos pivotes de equipo (`torneo_administradores` y `torneo_arbitros`
   con pista opcional) en lugar de uno solo con rol.
6. Nombres: `Participante` para la entidad que compite (pareja), con
   «pareja» solo en textos de interfaz.


## Lo que cambió al escribir el código

Anotado al cerrar la fase 1, como pide el briefing.

- El motor devuelve en `RepartoDeGrupos` la semilla que ha usado, para
  que la acción la persista sin tener que generarla ella.
- Los cuadros recién construidos llevan ids provisionales (1, 2, 3… por
  ronda y posición) en los orígenes; `Actions\Competicion\Cuadros` los
  reescribe por los reales al persistir. Un bye de primera ronda cierra su
  partido como walkover sin resultado y avanza al participante.
- `Set` lleva `esSuperTieBreak`: en un super tie-break los «juegos» son
  los puntos y no cuentan como juegos en la clasificación.
- `Puntuacion` lleva también cómo cuenta un walkover (sets y juegos por
  set), en lugar de fijarlo en el calculador.
- El `GeneradorRoundRobin` reparte las localías para que nadie sea
  siempre visitante y, con número fijo de partidos y grupo impar, usa un
  circulante (cada uno contra los k/2 siguientes y anteriores).
- Con tres o más empatados, la línea que la mini-liga separa de la
  siguiente se anota con el criterio `enfrentamiento_directo`, aunque
  dentro de la mini-liga lo decidiera la diferencia de sets.
- El `Planificador` solo recibe partidos con los dos participantes
  conocidos; la dependencia entre rondas solo se declara si el partido
  del que depende no está cerrado.
- `GenerarFranjas` usa la duración más larga de los formatos efectivos de
  las categorías del torneo más el margen, y no toca franjas existentes.
- `users` del organizador ya se creó en el commit 2 (la base central lo
  necesitaba para el propietario); el resto de tablas del organizador en
  el commit 3. `BaseDelOrganizadorSeeder` (formatos por defecto y nombre
  público) corre al dar de alta cualquier organizador.
- El seeder de torneos deja un torneo por estado, pero el «triple empate
  preparado» del de en juego no está garantizado: los resultados alternan
  ganadores.
- La verificación: las pruebas del motor se ejecutaron con un arnés local
  que imita a Pest (sin Laravel); las de feature quedan por ejecutar.
