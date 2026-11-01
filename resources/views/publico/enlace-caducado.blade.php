<x-layouts.publico titulo="Enlace caducado">
    <div class="mx-auto max-w-xl px-4 py-16">
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-6">
            <h1 class="text-xl font-semibold text-amber-900">Este enlace ya no vale</h1>
            <p class="mt-2 text-amber-900">Ha caducado o ya se usó. Pide otro: llega en un momento.</p>
            <a href="{{ route('jugador.acceso') }}" class="mt-4 inline-block rounded-lg px-5 py-2.5 font-medium text-white" style="background: var(--acento)">Pedir otro enlace</a>
        </div>
    </div>
</x-layouts.publico>
