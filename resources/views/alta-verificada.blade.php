<x-layouts.publico :titulo="$organizador ? 'Organización activada' : 'Enlace caducado'">
    <div class="mx-auto max-w-2xl px-4 py-16">
        @if ($organizador)
            <div class="rounded-xl border border-green-200 bg-green-50 p-6">
                <h1 class="text-xl font-semibold text-green-900">{{ $organizador->nombre }} ya está activa</h1>
                <p class="mt-2 text-green-900">Entra en tu panel con tu correo y tu contraseña:</p>
                <a href="{{ $organizador->url('panel') }}" class="mt-4 inline-block rounded-lg px-5 py-2.5 font-medium text-white" style="background: var(--acento)">Ir a mi panel</a>
                <p class="mt-4 text-sm text-green-900">Tu dirección pública es <strong>{{ $organizador->url() }}</strong>.</p>
            </div>
        @else
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-6">
                <h1 class="text-xl font-semibold text-amber-900">Este enlace no vale</h1>
                <p class="mt-2 text-amber-900">{{ $nombre ? 'El enlace de «'.$nombre.'» ha caducado o ya se usó.' : 'No hay ninguna organización con esa dirección.' }} Si no has podido activarla, vuelve a <a href="{{ route('alta') }}" class="underline">crearla</a> o escríbenos.</p>
            </div>
        @endif
    </div>
</x-layouts.publico>
