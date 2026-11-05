<div class="mx-auto max-w-2xl px-4 py-8" wire:poll.20s>
    <header class="flex items-center justify-between gap-3">
        <h1 class="text-2xl font-normal">Mesa</h1>
        <span class="text-sm text-neutral-500">{{ $usuario->nombre }}</span>
    </header>

    @if ($torneos->isEmpty())
        <p class="mt-6 text-neutral-600">No tienes torneos en juego asignados.</p>
    @else
        <div class="mt-4 grid gap-2 sm:grid-cols-3">
            <select wire:model.live="torneo" class="rounded-[3px] border border-neutral-300 px-2 py-2 text-sm" aria-label="Torneo">
                @foreach ($torneos as $t)
                    <option value="{{ $t->id }}">{{ $t->nombre }}</option>
                @endforeach
            </select>
            <select wire:model.live="dia" class="rounded-[3px] border border-neutral-300 px-2 py-2 text-sm" aria-label="Día">
                @foreach ($dias as $valor => $etiqueta)
                    <option value="{{ $valor }}">{{ $etiqueta }}</option>
                @endforeach
            </select>
            <select wire:model.live="pista" class="rounded-[3px] border border-neutral-300 px-2 py-2 text-sm" aria-label="Pista">
                <option value="">Todas mis pistas</option>
                @foreach ($pistas as $p)
                    <option value="{{ $p->id }}">{{ $p->nombre }}</option>
                @endforeach
            </select>
        </div>

        @if ($aviso) <p class="mt-4 rounded-[3px] bg-green-50 p-3 text-sm text-green-900">{{ $aviso }}</p> @endif
        @error('mesa') <p class="mt-4 rounded-[3px] bg-red-50 p-3 text-sm text-red-800">{{ $message }}</p> @enderror
        @error('portal') <p class="mt-4 rounded-[3px] bg-red-50 p-3 text-sm text-red-800">{{ $message }}</p> @enderror

        <ul class="mt-4 space-y-3">
            @forelse ($partidos as $p)
                <li @class(['tarjeta p-4', 'ring-2 ring-(--acento)' => $p['enJuego']]) wire:key="mesa-partido-{{ $p['id'] }}">
                    <div class="flex justify-between gap-2 text-sm text-neutral-500"><span>{{ $p['hora'] }} · {{ $p['pista'] }}@if ($p['orden']) · {{ $p['orden'] }}º @endif · {{ $p['categoria'] }}</span><span>{{ $p['estado']->etiqueta() }}</span></div>
                    <div class="mt-1 font-semibold">{{ $p['local'] }} – {{ $p['visitante'] }}</div>
                    @if ($p['resultado'] !== '') <div class="mt-1 tabular-nums">{{ $p['resultado'] }}</div> @endif

                    @if ($p['enJuego'] && $editando !== $p['id'])
                        @include('livewire.mesa._marcador', ['p' => $p])
                    @endif

                    @if ($editando === $p['id'])
                        @include('livewire.publico._formulario-resultado', ['local' => $p['local'], 'visitante' => $p['visitante'], 'conEspeciales' => true])
                    @elseif ($p['porEmpezar'])
                        <div class="mt-3 flex flex-wrap gap-2">
                            <button type="button" wire:click="empezarPartido({{ $p['id'] }})" class="boton px-4 py-1.5">Empezar partido</button>
                            <button type="button" wire:click="abrirResultado({{ $p['id'] }})" class="boton-claro px-4 py-1.5">Anotar resultado directamente</button>
                        </div>
                    @elseif ($p['enJuego'] && $p['encadenado'])
                        <button type="button" wire:click="terminarPartido({{ $p['id'] }})" class="mt-3 boton px-4 py-1.5">Terminar: guardar resultado</button>
                    @elseif ($p['abierto'])
                        <button type="button" wire:click="abrirResultado({{ $p['id'] }})" class="mt-2 text-sm font-medium enlace">Anotar resultado</button>
                    @elseif ($p['estado'] === \App\Enums\EstadoPartido::PendienteValidacion)
                        <button type="button" wire:click="validar({{ $p['id'] }})" class="mt-2 text-sm font-medium enlace">Validar</button>
                    @endif

                    @if ($p['whatsapp'] !== [])
                        <div class="mt-3 flex flex-wrap items-center gap-2 text-sm">
                            <span class="text-neutral-500">Avisar por WhatsApp:</span>
                            @foreach ($p['whatsapp'] as $enlace)
                                <a href="{{ $enlace['url'] }}" target="_blank" rel="noopener" class="rounded-full bg-green-50 px-3 py-1 font-medium text-green-800 hover:bg-green-100">{{ $enlace['nombre'] }}</a>
                            @endforeach
                        </div>
                    @endif
                </li>
            @empty
                <li class="text-neutral-600">No hay partidos con hora ese día en tus pistas.</li>
            @endforelse
        </ul>
    @endif
</div>
