# Propuesta: portal del jugador, panel cruzado y día de juego encadenado

Qué hay que cambiar para subir de nivel la aplicación en tres frentes:
el portal del jugador (sencillo, al estilo del portal de familias de
CEAS), el panel del organizador (todo cruzado y con una vista visual de
la competición) y el control del día de juego (partidos encadenados, en
vez de a horas fijas, con marcador en vivo, avisos por WhatsApp y
calentamientos cortos). Parte de lo que hay hoy en el repositorio; lo
que se decida aquí se anota después en `docs/briefing.md`.

Las decisiones que están marcadas con **[decidir]** cambian el trabajo
de forma importante y conviene cerrarlas antes de empezar ese bloque.

## 1. Portal del jugador

### Qué hay hoy

Una sola página `/jugador` con cinco pestañas (Inscripciones, Partidos,
Cuándo no puedo, Avisos, Mis datos) y una ficha de inscripción
`/jugador/inscripciones/{id}` para los niveles. Los grupos, la
clasificación y el cuadro no están en el portal: hay que ir a la ficha
pública del torneo. No hay un «próximo partido» destacado ni se ve la
pareja de un vistazo.

### Qué se propone

La misma estructura que el portal de familias de CEAS: cabecera con la
marca del organizador, un menú de cinco entradas, un saludo, cuatro
tarjetas resumen, la lista de cosas pendientes, lo próximo y las
tarjetas de los torneos. Todo en una columna en el móvil y en dos en
pantalla ancha. Sin pestañas dentro de la página: cada entrada del menú
es una ruta.

| Ruta | Página | Qué enseña |
| --- | --- | --- |
| `/jugador` | Inicio | Saludo («Hola, Ismael»; «Temporada · 2 torneos activos»). Cuatro tarjetas: **Próximo partido** (día y hora, o «después del partido de la pista 2»), **Tu pareja** (nombre y nivel del compañero del torneo más próximo), **Por validar** (resultados del rival esperando), **Torneos** (activos). Lista «Tienes N cosas pendientes»: validar un resultado, confirmar la pareja, poner el nivel, pago pendiente, apuntar cuándo no puedo. Bloque «Lo próximo»: tarjeta grande del próximo partido con rival, pista, hora estimada y marcador en vivo del partido anterior si va encadenado. Tarjetas «Tus torneos» con pareja, categoría y estado (Confirmada, En espera #3, Pendiente de pareja). Tarjeta «La app en el móvil» con el botón de instalar |
| `/jugador/torneos/{inscripcion}` | Mi torneo | Cabecera del torneo y la pareja (los dos jugadores, nivel, botón «Editar niveles»). Secciones: **Mis partidos** (jugados y por jugar, con anotar y validar resultado), **Mi grupo** (la clasificación de mi grupo con mi pareja resaltada y los resultados de todos sus partidos), **Los otros grupos** (la clasificación y los resultados de cada grupo de mi categoría, plegados), **Cuadro** (cuando lo hay, con mi cruce resaltado), **Cuándo no puedo** (si el torneo aún no tiene horarios). Sustituye a `/jugador/inscripciones/{id}` |
| `/jugador/partidos` | Partidos | Todos mis partidos de todos los torneos, los pendientes primero |
| `/jugador/avisos` | Avisos | Lo que hay hoy en la pestaña Avisos: canales (correo, push y, cuando esté, WhatsApp) e historial |
| `/jugador/datos` | Mis datos | El formulario de hoy, más el teléfono con prefijo (necesario para WhatsApp) y el borrado |

Los datos de los grupos ya los calcula `DatosDelTorneo` para la ficha
pública; el portal los reutiliza (con los nombres públicos y la columna
«Cruce previsto» que ya existe) y solo añade el resaltado de la propia
pareja. No hay lógica nueva de competición.

Estilo: el de la captura de CEAS. Fondo gris muy claro, tarjetas blancas
con borde suave y esquinas redondeadas, un solo color de acento (el del
organizador, que ya sirve para la PWA), tipografía normal sin títulos
enormes, etiquetas de estado en píldoras. El CSS base ya está en
`resources/css` (clases `tarjeta`, `boton`, `pestana`); se añaden las de
tarjeta resumen y lista de pendientes y se quita el patrón de pestañas.

### Implementación

- Un componente Livewire por página en `App\Livewire\Jugador\`
  (`Inicio`, `MiTorneo`, `MisPartidos`, `Avisos`, `MisDatos`) y un layout
  `layouts/jugador.blade.php` con la cabecera y el menú. El actual
  `PortalDelJugador` se reparte entre ellos y desaparece.
- Un servicio de lectura `App\Jugadores\ResumenDelJugador` que devuelve
  lo que necesita Inicio (próximo partido, pareja, pendientes), probado
  con un jugador con dos torneos en estados distintos.
- Las rutas antiguas redirigen a las nuevas: los enlaces de los correos
  ya enviados siguen funcionando.

## 2. Panel del organizador: todo cruzado y una vista visual

### Qué hay hoy

Recursos Filament separados (torneos, categorías, parejas, jugadores,
pistas, formatos, usuarios). Desde un torneo se llega a sus parejas y
desde una pareja a su edición, pero desde un jugador no se ve en qué
torneos está ni se puede saltar a ellos, y la competición se gestiona en
modales (clasificación, cuadro) sin una vista de conjunto.

### Enlaces cruzados

| Desde | Hacia | Cómo |
| --- | --- | --- |
| Ficha del jugador | Sus torneos | Página `ViewJugador` con sus datos y un gestor de relación «Torneos»: una fila por inscripción (torneo, categoría, pareja, estado, pago), con enlaces al torneo, a la categoría y a la pareja |
| Lista de inscritos de una categoría y lista de parejas de un torneo | Ficha del jugador | La columna «Jugadores» pasa a enlaces, uno por jugador |
| Partidos del torneo | Ficha del jugador y de la pareja | Los nombres de las parejas enlazan a la pareja; un desplegable en la fila lleva a cada jugador |
| Ficha de la pareja | Sus partidos y su grupo | Sección «Competición» con el grupo, la posición y los partidos de la pareja |
| Cualquier sitio | Cualquier cosa | Búsqueda global de Filament sobre jugadores (nombre, correo, teléfono), parejas y torneos |

### Vista visual de la competición

Una página nueva en el recurso de torneos, `CompeticionDelTorneo`
(`/panel/torneos/{id}/competicion`), que es la que se abre el día del
torneo:

- Selector de categoría arriba.
- **Grupos**: una tarjeta por grupo con la clasificación (con «Cruce
  previsto») y debajo sus partidos con el resultado o el estado. Anotar,
  validar o anular un resultado se hace ahí mismo con las acciones que ya
  existen en `AccionesDePartido`. Un indicador por grupo: jugados / total.
- **Cuadro**: el cuadro dibujado por rondas, con los partidos como
  tarjetas conectadas (CSS grid, sin librería), el tercer puesto y la
  consolación debajo. Los mismos botones de resultado.
- **Clasificados**: la lista de quién pasa con su origen, tal como la
  calcula el cierre de fase, y el botón «Cerrar fase» con el aviso de
  cruces.
- **Pistas** (cuando el torneo va encadenado, ver el bloque 3): una
  columna por pista con la cola de partidos y el marcador en vivo.

Los datos salen de `DatosDelTorneo` y `ProyectarCruces`, que ya existen;
la página solo los pinta con los nombres completos (en el panel no hay
privacidad de alias).

### Otras mejoras del panel

- **Panel de inicio** (`Panel.php`): tarjetas «Hoy» (partidos de hoy por
  pista, pendientes de validar, resultados anotados en la última hora),
  no solo la lista de torneos.
- **Línea de estado del torneo** en la cabecera de su ficha: Borrador →
  Inscripción → Sorteo → En juego → Finalizado, con lo que falta para
  pasar al siguiente (por ejemplo «3 parejas sin confirmar»).
- **Ficha de la pareja** con pestañas: Datos, Jugadores, Competición,
  Pagos y avisos recibidos.
- **Comunicaciones** con destinatarios a elegir: torneo, categoría, un
  grupo o una pareja.

## 3. Día de juego: partidos encadenados

### Qué hay hoy

Cada partido tiene `franja_id` (hora fija) y `pista_id`. El planificador
reparte los partidos en franjas por la duración del formato y publica
horarios. La mesa de árbitro anota resultados finales; no hay marcador
en vivo. El aviso «próximo partido» sale por push 30 minutos antes de la
franja.

### Qué se propone

Un modo de horario por torneo, **[decidir]** `modo_horario`:

- `fijo`: lo de hoy.
- `encadenado`: los partidos tienen pista y **orden en la pista**, no
  hora. Cuando acaba uno empieza el siguiente. Se enseña una hora
  **estimada** que se recalcula sola conforme se cierran partidos.

Modelo:

- `partidos.orden_en_pista` (entero) y `partidos.inicio_estimado`,
  `partidos.empezado_en`, `partidos.terminado_en`.
- `partidos.marcador_en_vivo` (JSON: sets cerrados y juegos del set en
  curso, quién saca no hace falta), que la mesa actualiza punto a punto
  o juego a juego.
- `torneos.calentamiento_primero` (15 min por defecto) y
  `torneos.calentamiento_resto` (5 min por defecto).

Motor (puro, en `App\Competicion`):

- `Encadenador`: a partir de la lista de partidos a jugar, las pistas y
  las restricciones (descanso mínimo, jugadores que no se solapan,
  prioridad entre categorías), devuelve la cola de cada pista. Reutiliza
  la heurística de `Planificador` con franjas virtuales: una franja tras
  otra por pista, de la duración estimada de cada partido.
- `EstimadorDeHorario`: con la cola de cada pista, la hora de inicio, lo
  que ya se ha jugado (`empezado_en` / `terminado_en` reales) y la
  duración del formato, calcula `inicio_estimado` de cada partido que
  queda. La duración de un partido = duración del formato +
  calentamiento, donde el calentamiento es 15 min si para alguna de las
  dos parejas es su primer partido del día y 5 min si las dos ya han
  jugado. El primer partido de cada pista empieza 15 min después de la
  hora de apertura: calientan antes.

Acciones:

- `EncadenarPartidos(Torneo)`: llama al `Encadenador` y guarda orden y
  estimaciones. Reordenar a mano es arrastrar en la columna de la pista.
- `EmpezarPartido(Partido)`: estado `en_juego`, `empezado_en = now()`,
  reestima la cola de la pista y avisa al **siguiente** de esa pista
  («Sois los siguientes en la pista 2: acaba de empezar García/López –
  Pérez/Ruiz»).
- `AnotarMarcador(Partido, marcador)`: guarda `marcador_en_vivo`. Cuando
  el partido entra en el último set, o en los últimos juegos (por
  ejemplo desde 4-4 del set decisivo), avisa al siguiente de la pista
  («El partido anterior va 6-3 4-4: id preparándoos»). Un solo aviso por
  partido y pareja; se anota en el partido para no repetir.
- `RegistrarResultado` (la de hoy) pone `terminado_en`, reestima y avisa
  al siguiente («Os toca: pista 2»).

Quién anota: la mesa de árbitro que ya existe, con una pantalla por
pista en el móvil (`/mesa/pista/{id}`): botones grandes «Juego local» /
«Juego visitante», «Set cerrado», «Terminar partido». Si no hay mesa,
el torneo puede dejar que lo haga un jugador de cada pareja (el mismo
permiso de resultados por jugadores de hoy).

Público y jugadores: la ficha del torneo y el tablero enseñan «En pista
2: García/López – Pérez/Ruiz · 6-3 4-4» y «Siguientes: …, hacia las
18:40». El portal del jugador enseña «Tu próximo partido: después del que
está en la pista 2 (va 6-3 4-4). Calienta 5 min».

### Avisos por WhatsApp

**[decidir]** El canal oficial es la **WhatsApp Business Platform de
Meta (Cloud API)**. Es lo único que vale para un producto: los servicios
«rápidos» que automatizan WhatsApp Web (Evolution API, WAHA, Baileys)
van contra las condiciones de WhatsApp y acaban con el número bloqueado,
a menudo en mitad de un torneo.

Dos formas de contratarla:

| Opción | Pros | Contras |
| --- | --- | --- |
| **Meta Cloud API directa** | Sin intermediario ni cuota mensual: se paga solo por conversación. Paquete Laravel listo (`netflie/laravel-notification-whatsapp`), que encaja como canal de notificación igual que el push | Hay que crear una cuenta de Meta Business, verificar la empresa, dar de alta un número que no esté ya en WhatsApp personal y que aprueben las plantillas (uno o dos días) |
| **Twilio (sobre la misma API)** | Alta más guiada, sandbox para probar en una tarde, SMS de respaldo con el mismo código | Un sobrecoste por mensaje además del de Meta, y la misma verificación de Meta al final |

Recomendación: **Meta Cloud API directa**, con Twilio solo si se quiere
probar antes de verificar la empresa. En España una conversación de
servicio (categoría «utility») cuesta unos pocos céntimos; conviene
mirar la tarifa vigente de Meta antes de fijar el precio del plan. Para
un torneo de 32 parejas con 4 avisos por pareja son unos 130 mensajes,
es decir, unos pocos euros por torneo.

Cómo encaja en lo que hay:

- Un canal `CanalWhatsApp` junto a `CanalPush`, y la preferencia
  `avisos.whatsapp` del jugador junto a correo y push. `AvisoAlJugador`
  decide el canal como hoy; cada aviso declara su plantilla y sus
  variables.
- Como los mensajes los inicia la organización fuera de la ventana de 24
  horas, van por **plantillas aprobadas**. Hacen falta cuatro: «Sois los
  siguientes», «Os toca», «Resultado por validar» y «Aviso de la
  organización». Las plantillas se escriben una vez por plataforma, no
  por organizador.
- El jugador da su teléfono con prefijo y marca el consentimiento de
  WhatsApp al inscribirse (obligatorio para Meta). Sin consentimiento,
  push y correo como hoy.
- El número de WhatsApp es de la plataforma (un solo número para todos
  los organizadores, con el nombre del organizador al principio del
  mensaje), que es más barato y no obliga a cada club a verificarse. Un
  club con su propio número es un ajuste de plan para más adelante.
- Los envíos salen por la cola, con reintento, y el resultado
  (entregado, leído, fallido) se guarda en `push_envios` renombrada a
  `envios` con la columna `canal`.

SMS como respaldo (Twilio o un proveedor español como Altiria) solo si
hay jugadores sin WhatsApp; no se hace de entrada.

## 4. Orden de trabajo

Cada bloque es una rama y una pull request, y deja la aplicación
entera y verde. Están ordenados para que lo que se ve primero sea lo que
más valor da y menos decisiones pendientes tiene.

| Orden | Bloque | Depende de | Tamaño | Estado |
| --- | --- | --- | --- | --- |
| 1 | Panel: enlaces cruzados jugador ↔ pareja ↔ torneo, búsqueda global, ficha del jugador con sus torneos | Nada | Pequeño | Hecho |
| 2 | Panel: página «Competición» con grupos, cuadro dibujado y clasificados | 1 | Medio | Hecho (los clasificados se ven en la tabla de cada grupo, no en una lista aparte) |
| 3 | Portal del jugador: layout nuevo, Inicio y Mi torneo | Nada | Medio | Hecho |
| 4 | Portal del jugador: Partidos, Avisos y Mis datos; redirecciones; retirar `PortalDelJugador` | 3 | Pequeño | Hecho, dentro del 3 |
| 5 | Panel de inicio «Hoy» y línea de estado del torneo | 2 | Pequeño | El inicio del panel ya tenía «Partidos del día» y «Por revisar»; se le añade el salto a «Competición». La línea de estado queda como está (el botón «Estado» de la ficha, con pasar y volver) |
| 6 | Motor: `Encadenador` y `EstimadorDeHorario` con sus pruebas | Decidir `modo_horario` y calentamientos | Medio | Pendiente |
| 7 | Partidos encadenados: modelo, acciones, mesa por pista con marcador en vivo, columna de pistas en «Competición», ficha pública y portal | 6 | Grande | Pendiente |
| 8 | WhatsApp: canal, plantillas, consentimiento, envíos y preferencia del jugador | Cuenta de Meta Business y número | Medio | Pendiente |

Lo que no entra, para no crecer sin tope: pasarela de pago, cuentas de
jugador globales entre organizadores, WebSockets (el tablero y la mesa
siguen con polling de Livewire cada pocos segundos, que basta para un
marcador en vivo).

## 5. Qué hay que decidir antes de empezar

- [ ] `modo_horario` por torneo o encadenado siempre que haya mesa. La
      propuesta es por torneo, con `fijo` por defecto, para no romper lo
      que ya funciona.
- [ ] Calentamientos: 15 y 5 minutos como propone el briefing, o
      configurables por torneo (la propuesta: configurables con esos
      valores por defecto).
- [ ] Quién anota el marcador en vivo: solo la mesa, o también los
      jugadores cuando el torneo lo permite.
- [ ] WhatsApp: Meta directa o Twilio para empezar; y quién da de alta la
      cuenta de Meta Business y el número.
- [ ] Si el aviso de «sois los siguientes» sale al empezar el partido
      anterior, al entrar en el último set, o las dos cosas (la
      propuesta: las dos, con texto distinto).
