{{--
    Aviso discreto para instalar la web como aplicación. En Android y en
    Chrome de ordenador, con el aviso del navegador; en iPhone y iPad, que
    no lo tienen, con las instrucciones de Safari. Desaparece si ya está
    instalada o si se cierra (se recuerda en este navegador).
--}}
<div
    x-data="{
        visible: false,
        ios: false,
        aviso: null,
        init() {
            let instalada = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true
            let cerrado = false
            try { cerrado = localStorage.getItem('pwa-instalar-cerrado') === '1' } catch (e) {}
            if (instalada || cerrado) return
            this.ios = /iphone|ipad|ipod/i.test(navigator.userAgent)
            if (this.ios) { this.visible = true; return }
            window.addEventListener('beforeinstallprompt', (e) => { e.preventDefault(); this.aviso = e; this.visible = true })
        },
        async instalar() {
            if (!this.aviso) return
            this.aviso.prompt()
            await this.aviso.userChoice
            this.visible = false
        },
        cerrar() {
            this.visible = false
            try { localStorage.setItem('pwa-instalar-cerrado', '1') } catch (e) {}
        },
    }"
    x-show="visible"
    x-cloak
    {{ $attributes->merge(['class' => 'rounded-xl border border-neutral-200 bg-white p-4 text-sm']) }}
>
    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="font-medium">Ten los torneos a mano</p>
            <p x-show="!ios" class="mt-1 text-neutral-600">Instala la web en el móvil: verás horarios y resultados al momento y te llegarán los avisos.</p>
            <p x-show="ios" class="mt-1 text-neutral-600">En iPhone: toca <strong>Compartir</strong> (el cuadrado con la flecha) y después <strong>«Añadir a pantalla de inicio»</strong>. Ábrela desde el icono para recibir avisos.</p>
        </div>
        <button type="button" x-on:click="cerrar()" class="text-neutral-400 hover:text-neutral-700" aria-label="Cerrar">✕</button>
    </div>
    <button type="button" x-show="!ios" x-on:click="instalar()" class="mt-3 rounded-lg px-4 py-2 font-medium text-white" style="background: var(--acento)">Instalar</button>
</div>
