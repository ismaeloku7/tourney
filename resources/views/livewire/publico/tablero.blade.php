{{-- El tablero para la tele: en pista, próximos y últimos resultados. Se refresca cada 5 segundos. --}}
<div class="flex min-h-screen flex-col gap-6 p-8" wire:poll.5s>
    <header class="flex items-baseline justify-between gap-4">
        <h1 class="text-4xl font-bold">{{ $torneo->nombre }}</h1>
        <span class="text-3xl tabular-nums text-neutral-400">{{ now()->format('H:i') }}</span>
    </header>

    <div class="grid flex-1 gap-6 lg:grid-cols-3">
        <section>
            <h2 class="text-2xl font-semibold" style="color: var(--acento)">En pista</h2>
            <ul class="mt-4 space-y-3">
                @forelse ($enPista as $p)
                    <li class="rounded-xl bg-neutral-900 p-4">
                        <div class="text-sm text-neutral-400">{{ $p['pista'] }} · {{ $p['categoria'] }}</div>
                        <div class="mt-1 text-xl font-semibold">{{ $p['partido'] }}</div>
                    </li>
                @empty
                    <li class="text-xl text-neutral-500">Ahora no hay partidos en juego.</li>
                @endforelse
            </ul>
        </section>
        <section>
            <h2 class="text-2xl font-semibold" style="color: var(--acento)">Próximos</h2>
            <ul class="mt-4 space-y-3">
                @forelse ($proximos as $p)
                    <li class="rounded-xl bg-neutral-900 p-4">
                        <div class="text-sm text-neutral-400">{{ $p['horaCorta'] }} · {{ $p['pista'] }} · {{ $p['categoria'] }}</div>
                        <div class="mt-1 text-xl font-semibold">{{ $p['partido'] }}</div>
                    </li>
                @empty
                    <li class="text-xl text-neutral-500">No quedan partidos con hora.</li>
                @endforelse
            </ul>
        </section>
        <section>
            <h2 class="text-2xl font-semibold" style="color: var(--acento)">Últimos resultados</h2>
            <ul class="mt-4 space-y-3">
                @forelse ($ultimos as $p)
                    <li class="rounded-xl bg-neutral-900 p-4">
                        <div class="text-sm text-neutral-400">{{ $p['categoria'] }}</div>
                        <div class="mt-1 text-xl font-semibold">{{ $p['partido'] }}</div>
                        <div class="mt-1 text-lg tabular-nums text-neutral-300">{{ $p['resultado'] }}</div>
                    </li>
                @empty
                    <li class="text-xl text-neutral-500">Todavía no hay resultados.</li>
                @endforelse
            </ul>
        </section>
    </div>
</div>
