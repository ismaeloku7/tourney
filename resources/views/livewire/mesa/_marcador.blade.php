{{-- El marcador en vivo de un partido en juego, con botones grandes para el móvil. Espera $p con local, visitante, id, marcador y marcadorTexto. --}}
<div class="mt-3 rounded-[3px] bg-neutral-50 p-3">
    <div class="flex items-baseline justify-between gap-3">
        <span class="text-xs text-neutral-500">Marcador en vivo</span>
        <span class="text-xl font-semibold tabular-nums">{{ $p['marcadorTexto'] !== '' ? $p['marcadorTexto'] : '0-0' }}</span>
    </div>
    <div class="mt-2 grid grid-cols-2 gap-2 text-sm">
        @foreach ([[true, $p['local']], [false, $p['visitante']]] as [$esLocal, $nombre])
            <div class="rounded-[3px] bg-white p-2 ring-1 ring-tema-borde">
                <div class="truncate text-xs text-neutral-500">{{ $nombre }}</div>
                <div class="mt-1 flex items-center justify-between gap-2">
                    <button type="button" wire:click="quitarJuego({{ $p['id'] }}, {{ $esLocal ? 'true' : 'false' }})" class="size-9 rounded-full bg-neutral-100 text-lg font-medium" aria-label="Quitar juego a {{ $nombre }}">−</button>
                    <span class="text-2xl font-semibold tabular-nums">{{ $p['marcador']['juegos'][$esLocal ? 0 : 1] }}</span>
                    <button type="button" wire:click="juego({{ $p['id'] }}, {{ $esLocal ? 'true' : 'false' }})" class="size-9 rounded-full text-lg font-medium text-white" style="background: var(--acento)" aria-label="Juego para {{ $nombre }}">+</button>
                </div>
            </div>
        @endforeach
    </div>
    <div class="mt-2 flex flex-wrap gap-2 text-sm">
        <button type="button" wire:click="cerrarSet({{ $p['id'] }})" class="boton-claro px-3 py-1">Cerrar set</button>
        @if ($p['marcador']['sets'] !== [])
            <button type="button" wire:click="reabrirSet({{ $p['id'] }})" class="rounded-full px-3 py-1 text-neutral-600 underline">Reabrir último set</button>
        @endif
    </div>
</div>
