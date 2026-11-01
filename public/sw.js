/*
 * Service worker de la web de cada organizador: la hace instalable,
 * recibe las notificaciones push y deja ver sin cobertura lo último que
 * se vio de un torneo.
 *
 * - Páginas públicas de torneos y la portada: red primero y, si no hay,
 *   la última copia guardada (son públicas: no llevan datos de nadie).
 * - Portal del jugador, mesa, panel, inscripción e invitaciones: nunca se
 *   guardan; sin red, el aviso de que no hay conexión.
 * - Estáticos (Vite, iconos): caché primero; llevan la versión en la URL.
 * - Nada que no sea GET: inscribirse o anotar un resultado exige conexión.
 *
 * Al cambiar este archivo, sube VERSION para renovar las cachés.
 */

var VERSION = 'v1';
var ESTATICOS = 'estaticos-' + VERSION;
var PAGINAS = 'paginas-' + VERSION;
var AVISO = '/sin-conexion.html';

self.addEventListener('install', function (evento) {
    evento.waitUntil(
        caches.open(ESTATICOS)
            .then(function (cache) { return cache.add(AVISO); })
            .then(function () { return self.skipWaiting(); })
    );
});

self.addEventListener('activate', function (evento) {
    evento.waitUntil(
        caches.keys().then(function (nombres) {
            return Promise.all(nombres.map(function (nombre) {
                return nombre === ESTATICOS || nombre === PAGINAS ? null : caches.delete(nombre);
            }));
        }).then(function () { return self.clients.claim(); })
    );
});

function esEstatico(url) {
    return (/^\/(build|pwa)\//.test(url.pathname) || /^\/icono-[\w-]+\.png$/.test(url.pathname))
        && /\.(css|js|png|svg|ico|webp|woff2?)$/i.test(url.pathname);
}

/** Las páginas públicas que se pueden guardar: la portada y las de un torneo (no su inscripción). */
function esPaginaPublica(url) {
    if (url.pathname === '/') {
        return true;
    }
    return /^\/torneos\/[^/]+(\/(tablero|pista\/\d+))?\/?$/.test(url.pathname);
}

self.addEventListener('fetch', function (evento) {
    var peticion = evento.request;
    if (peticion.method !== 'GET') {
        return;
    }

    var url = new URL(peticion.url);
    if (url.origin !== self.location.origin) {
        return;
    }

    if (peticion.mode === 'navigate') {
        var guardable = esPaginaPublica(url);
        evento.respondWith(
            fetch(peticion).then(function (respuesta) {
                if (guardable && respuesta && respuesta.ok && respuesta.type === 'basic') {
                    var copia = respuesta.clone();
                    caches.open(PAGINAS).then(function (cache) { cache.put(peticion, copia); });
                }
                return respuesta;
            }).catch(function () {
                var copia = guardable ? caches.match(peticion, { cacheName: PAGINAS }) : Promise.resolve(undefined);
                return copia.then(function (guardada) {
                    return guardada || caches.match(AVISO).then(function (aviso) {
                        return aviso || new Response('Sin conexión.', { status: 503, headers: { 'Content-Type': 'text/plain; charset=utf-8' } });
                    });
                });
            })
        );
        return;
    }

    if (!esEstatico(url)) {
        return;
    }

    evento.respondWith(
        caches.match(peticion).then(function (guardado) {
            return guardado || fetch(peticion).then(function (respuesta) {
                if (respuesta && respuesta.ok && respuesta.type === 'basic') {
                    var copia = respuesta.clone();
                    caches.open(ESTATICOS).then(function (cache) { cache.put(peticion, copia); });
                }
                return respuesta;
            });
        })
    );
});

/* Notificaciones push (app/Push): título, una frase y adónde lleva. */
self.addEventListener('push', function (evento) {
    var datos = {};
    try {
        datos = evento.data ? evento.data.json() : {};
    } catch (e) {
        datos = {};
    }

    evento.waitUntil(self.registration.showNotification(datos.titulo || 'Aviso del torneo', {
        body: datos.texto || '',
        icon: '/icono-192.png',
        badge: '/icono-192.png',
        tag: datos.etiqueta || undefined,
        data: { url: datos.url || '/' }
    }));
});

self.addEventListener('notificationclick', function (evento) {
    evento.notification.close();
    var url = (evento.notification.data && evento.notification.data.url) || '/';

    evento.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (ventanas) {
            for (var i = 0; i < ventanas.length; i++) {
                if ('navigate' in ventanas[i] && new URL(ventanas[i].url).origin === self.location.origin) {
                    return ventanas[i].navigate(url).then(function (v) { return v && v.focus(); });
                }
            }
            return self.clients.openWindow(url);
        })
    );
});
