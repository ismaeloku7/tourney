{{-- Los partidos del día en una pista, para el QR colgado en ella. Se refresca cada 15 segundos. --}}
<div class="mx-auto max-w-2xl px-4 py-8" wire:poll.15s>
    <p class="text-sm text-neutral-500"><a href="{{ route('torneo.ficha', $torneo) }}" class="underline">{{ $torneo->nombre }}</a></p>
    <h1 class="mt-1 text-2xl font-semibold">{{ $pista->nombre }}</h1>
    <p class="text-neutral-600">{{ ucfirst($dia) }}</p>

    <ul class="mt-6 space-y-3">
        @forelse ($partidos as $p)
            <li class="rounded-xl border border-neutral-200 bg-white p-4">
                <div class="flex justify-between gap-3 text-sm text-neutral-500"><span>{{ $p['horaCorta'] }} · {{ $p['categoria'] }}</span><span>{{ $p['estado']->etiqueta() }}</span></div>
                <div class="mt-1 font-semibold">{{ $p['partido'] }}</div>
                @if ($p['resultado'] !== '')
                    <div class="mt-1 tabular-nums">{{ $p['resultado'] }}</div>
                @endif
            </li>
        @empty
            <li class="text-neutral-600">No hay partidos en esta pista este día.</li>
        @endforelse
    </ul>

    @if ($qr)
        <figure class="mt-10 flex flex-col items-center gap-2 text-sm text-neutral-500">
            <img src="{{ $qr }}" alt="Código QR de esta página" class="size-48">
            <figcaption>Para colgar en la pista: lleva a esta página.</figcaption>
        </figure>
    @endif
</div>
