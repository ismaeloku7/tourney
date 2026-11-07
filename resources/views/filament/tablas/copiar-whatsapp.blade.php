{{-- El texto de un listado de parejas para pegarlo en WhatsApp, con su botón de copiar. --}}
<div
    x-data="{
        copiado: false,
        copiar() {
            const texto = this.$refs.texto;
            const hecho = () => { this.copiado = true; setTimeout(() => this.copiado = false, 2500); };
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(texto.value).then(hecho);
            } else {
                texto.select();
                document.execCommand('copy');
                hecho();
            }
        },
    }"
    class="grid gap-3"
>
    @if ($texto === '')
        <p class="text-sm text-gray-500 dark:text-gray-400">No hay parejas con esta búsqueda y estos filtros.</p>
    @else
        <textarea
            x-ref="texto"
            readonly
            rows="14"
            x-on:focus="$el.select()"
            class="block w-full rounded-lg border border-gray-300 bg-white p-3 font-mono text-sm text-gray-950 dark:border-white/10 dark:bg-gray-900 dark:text-white"
        >{{ $texto }}</textarea>

        <div class="flex items-center gap-3">
            <x-filament::button icon="heroicon-o-clipboard-document" x-on:click="copiar()">
                Copiar
            </x-filament::button>
            <span x-show="copiado" x-cloak class="text-sm font-medium text-success-600 dark:text-success-400">
                Copiado. Péguelo en WhatsApp.
            </span>
        </div>
    @endif
</div>
