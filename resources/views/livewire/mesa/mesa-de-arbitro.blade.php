<div class="mx-auto max-w-2xl px-4 py-8">
    <header class="flex items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold">Mesa</h1>
        <span class="text-sm text-neutral-500">{{ $usuario->nombre }}</span>
    </header>

    @if ($torneos->isEmpty())
        <p class="mt-6 text-neutral-600">No tienes torneos en juego asignados.</p>
    @else
        <div class="mt-4 grid gap-2 sm:grid-cols-3">
            <select wire:model.live="torneo" class="rounded-lg border border-neutral-300 px-2 py-2 text-sm" aria-label="Torneo">
                @foreach ($torneos as $t)
                    <option value="{{ $t->id }}">{{ $t->nombre }}</option>
                @endforeach
            </select>
            <select wire:model.live="dia" class="rounded-lg border border-neutral-300 px-2 py-2 text-sm" aria-label="Día">
                @foreach ($dias as $valor => $etiqueta)
                    <option value="{{ $valor }}">{{ $etiqueta }}</option>
                @endforeach
            </select>
            <select wire:model.live="pista" class="rounded-lg border border-neutral-300 px-2 py-2 text-sm" aria-label="Pista">
                <option value="">Todas mis pistas</option>
                @foreach ($pistas as $p)
                    <option value="{{ $p->id }}">{{ $p->nombre }}</option>
                @endforeach
            </select>
        </div>

        @if ($aviso) <p class="mt-4 rounded-lg bg-green-50 p-3 text-sm text-green-900">{{ $aviso }}</p> @endif
        @error('mesa') <p class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-800">{{ $message }}</p> @enderror

        <ul class="mt-4 space-y-3">
            @forelse ($partidos as $p)
                <li class="rounded-xl border border-neutral-200 bg-white p-4">
                    <div class="flex justify-between gap-2 text-sm text-neutral-500"><span>{{ $p['hora'] }} · {{ $p['pista'] }} · {{ $p['categoria'] }}</span><span>{{ $p['estado']->etiqueta() }}</span></div>
                    <div class="mt-1 font-semibold">{{ $p['local'] }} – {{ $p['visitante'] }}</div>
                    @if ($p['resultado'] !== '') <div class="mt-1 tabular-nums">{{ $p['resultado'] }}</div> @endif
                    @if ($editando === $p['id'])
                        @include('livewire.publico._formulario-resultado', ['local' => $p['local'], 'visitante' => $p['visitante'], 'conEspeciales' => true])
                    @elseif ($p['abierto'])
                        <button type="button" wire:click="abrirResultado({{ $p['id'] }})" class="mt-2 text-sm font-medium underline" style="color: var(--acento)">Anotar resultado</button>
                    @elseif ($p['estado'] === \App\Enums\EstadoPartido::PendienteValidacion)
                        <button type="button" wire:click="validar({{ $p['id'] }})" class="mt-2 text-sm font-medium underline" style="color: var(--acento)">Validar</button>
                    @endif
                </li>
            @empty
                <li class="text-neutral-600">No hay partidos con hora ese día en tus pistas.</li>
            @endforelse
        </ul>
    @endif
</div>
