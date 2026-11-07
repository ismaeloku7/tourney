{{-- El enlace de acceso directo de una pareja, con su botón de copiar y su QR. Espera $url, $qr, $jugador y $ultimoUso. --}}
<div
    x-data="{
        copiado: false,
        copiar() {
            const campo = this.$refs.enlace;
            const hecho = () => { this.copiado = true; setTimeout(() => this.copiado = false, 2500); };
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(campo.value).then(hecho);
            } else {
                campo.select();
                document.execCommand('copy');
                hecho();
            }
        },
    }"
    class="grid gap-3"
>
    <input
        x-ref="enlace"
        type="text"
        readonly
        value="{{ $url }}"
        x-on:focus="$el.select()"
        class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 font-mono text-sm text-gray-950 dark:border-white/10 dark:bg-gray-900 dark:text-white"
    >

    <div class="flex flex-wrap items-center gap-3">
        <x-filament::button icon="heroicon-o-clipboard-document" x-on:click="copiar()">Copiar enlace</x-filament::button>
        <span x-show="copiado" x-cloak class="text-sm font-medium text-success-600 dark:text-success-400">Copiado.</span>
    </div>

    @if ($qr)
        {{-- Sobre blanco siempre, para que se escanee bien también en modo oscuro. --}}
        <div class="flex justify-center">
            <img src="{{ $qr }}" alt="QR del enlace de acceso" width="240" height="240" class="rounded-lg bg-white p-3 shadow-sm ring-1 ring-gray-200">
        </div>
        <p class="text-center text-xs text-gray-500 dark:text-gray-400">Que lo escaneen con la cámara del móvil: entran directamente.</p>
    @endif

    <p class="text-sm text-gray-500 dark:text-gray-400">
        @if ($jugador)
            Entra como {{ $jugador }}.
        @endif
        {{ $ultimoUso ? 'Usado por última vez el '.$ultimoUso.'.' : 'Todavía no se ha usado.' }}
    </p>
</div>
