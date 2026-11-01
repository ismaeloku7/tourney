{{--
    Activar las notificaciones push en este dispositivo. Habla con el
    componente Livewire de la página: `guardarPush(suscripcion, dispositivo)`
    y `quitarPush(endpoint)`. En iPhone y iPad solo funcionan con la web
    añadida a la pantalla de inicio (iOS 16.4 o posterior). Copiado de CEAS.
--}}
@props(['texto' => 'Activar las notificaciones en este móvil', 'siguiendo' => false])
@php($clave = \App\Push\Push::clavePublica())
@if ($clave !== null)
    <div
        x-data="{
            estado: 'cargando',
            ocupado: false,
            clave: @js($clave),
            async init() {
                let ios = /iphone|ipad|ipod/i.test(navigator.userAgent)
                let instalada = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true
                if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
                    this.estado = ios && !instalada ? 'instalar' : 'sin-soporte'
                    return
                }
                if (Notification.permission === 'denied') { this.estado = 'denegado'; return }
                let registro = await navigator.serviceWorker.getRegistration('/')
                let suscripcion = registro ? await registro.pushManager.getSubscription() : null
                // Para seguir un torneo, no basta con tener el push: hay que seguir este.
                if (suscripcion && @js((bool) $siguiendo)) {
                    this.estado = (await $wire.sigue(suscripcion.endpoint)) ? 'activo' : 'inactivo'
                    return
                }
                this.estado = suscripcion ? 'activo' : 'inactivo'
            },
            claveEnBytes() {
                let base = this.clave.replace(/-/g, '+').replace(/_/g, '/')
                let texto = atob(base + '='.repeat((4 - base.length % 4) % 4))
                return Uint8Array.from(texto, (c) => c.charCodeAt(0))
            },
            dispositivo() {
                let ua = navigator.userAgent
                if (/iphone/i.test(ua)) return 'iPhone'
                if (/ipad/i.test(ua)) return 'iPad'
                if (/android/i.test(ua)) return 'Android'
                if (/macintosh/i.test(ua)) return 'Mac'
                if (/windows/i.test(ua)) return 'Windows'
                return 'Otro'
            },
            async activar() {
                this.ocupado = true
                try {
                    let registro = await navigator.serviceWorker.register('/sw.js', { scope: '/' })
                    await navigator.serviceWorker.ready
                    let permiso = await Notification.requestPermission()
                    if (permiso !== 'granted') { this.estado = permiso === 'denied' ? 'denegado' : 'inactivo'; return }
                    let suscripcion = await registro.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: this.claveEnBytes() })
                    await $wire.guardarPush(suscripcion.toJSON(), this.dispositivo())
                    this.estado = 'activo'
                } catch (e) {
                    this.estado = 'error'
                } finally {
                    this.ocupado = false
                }
            },
            async desactivar() {
                this.ocupado = true
                try {
                    let registro = await navigator.serviceWorker.getRegistration('/')
                    let suscripcion = registro ? await registro.pushManager.getSubscription() : null
                    if (suscripcion) { await $wire.quitarPush(suscripcion.endpoint); await suscripcion.unsubscribe() }
                    this.estado = 'inactivo'
                } finally {
                    this.ocupado = false
                }
            },
        }"
        {{ $attributes->merge(['class' => 'text-sm']) }}
    >
        <template x-if="estado === 'inactivo' || estado === 'error'">
            <div>
                <button type="button" x-on:click="activar()" x-bind:disabled="ocupado" class="rounded-lg px-4 py-2 font-medium text-white disabled:opacity-60" style="background: var(--acento)">🔔 {{ $texto }}</button>
                <p x-show="estado === 'error'" class="mt-1 text-neutral-600">No se han podido activar. Vuelve a intentarlo en un rato.</p>
            </div>
        </template>
        <template x-if="estado === 'activo'">
            <p><strong>✓ {{ $siguiendo ? 'Sigues este torneo en este dispositivo.' : 'Notificaciones activadas en este dispositivo.' }}</strong> <button type="button" x-on:click="desactivar()" x-bind:disabled="ocupado" class="ml-2 underline">Desactivar</button></p>
        </template>
        <template x-if="estado === 'denegado'">
            <p class="text-neutral-600">Las notificaciones están bloqueadas para esta web. Se pueden permitir en los ajustes del navegador o del teléfono.</p>
        </template>
        <template x-if="estado === 'instalar'">
            <p class="text-neutral-600">En iPhone, primero añade la web a la pantalla de inicio (Compartir › «Añadir a pantalla de inicio») y ábrela desde el icono.</p>
        </template>
        <template x-if="estado === 'sin-soporte'">
            <p class="text-neutral-600">Este navegador no admite notificaciones.</p>
        </template>
    </div>
@endif
