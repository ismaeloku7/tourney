{{-- Un partido del jugador en una tarjeta, con anotar y validar si toca. Espera $p (la fila) y $editando. --}}
<li class="tarjeta p-4" wire:key="partido-{{ $p['id'] }}">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <span class="text-xs text-tema-suave">{{ $p['torneo'] }} · {{ $p['categoria'] }}</span>
        <span class="text-sm font-medium text-tema-azul">{{ $p['hora'] ?? ($p['porJugar'] ? 'Sin hora todavía' : '') }}</span>
    </div>
    <div class="mt-1 text-sm font-semibold">{{ $p['texto'] }}</div>
    <div class="mt-1 flex flex-wrap items-center gap-2 text-sm">
        @if ($p['resultado'] !== '')
            <span class="tabular-nums font-medium">{{ $p['resultado'] }}</span>
            @if ($p['gane'] === true) <span class="rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-800">Ganado</span>
            @elseif ($p['gane'] === false) <span class="rounded-full bg-tema-fondo px-2 py-0.5 text-xs font-medium text-tema-suave">Perdido</span>
            @endif
        @else
            <span class="text-tema-suave">{{ $p['estado']->etiqueta() }}</span>
        @endif
    </div>
    @if ($editando === $p['id'])
        @include('livewire.publico._formulario-resultado', ['local' => $p['local'], 'visitante' => $p['visitante'], 'conEspeciales' => false])
    @elseif ($p['puedeAnotar'] || $p['puedeValidar'])
        <div class="mt-3 flex flex-wrap gap-2">
            @if ($p['puedeAnotar'])
                <button type="button" wire:click="abrirResultado({{ $p['id'] }})" class="boton-claro px-4 py-1.5">Anotar resultado</button>
            @endif
            @if ($p['puedeValidar'])
                <button type="button" wire:click="validar({{ $p['id'] }})" class="boton px-4 py-1.5">Es correcto: validar</button>
            @endif
        </div>
    @endif
</li>
