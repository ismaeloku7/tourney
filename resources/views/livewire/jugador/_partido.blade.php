{{-- Un partido del jugador en una tarjeta, con anotar y validar si toca. Espera $p (la fila), $editando y, en su torneo, $enSuTorneo (no repite torneo ni categoría). --}}
<li class="tarjeta p-4" wire:key="partido-{{ $p['id'] }}">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <span class="text-xs font-semibold uppercase tracking-[0.12em] text-tema-suave">{{ ($enSuTorneo ?? false) ? 'Contra' : $p['torneo'].' · '.$p['categoria'] }}</span>
        <div class="flex flex-wrap items-center gap-1.5">
            @if ($p['hora'] !== null || $p['porJugar'])
                <span class="rounded-full bg-tema-fondo px-2.5 py-0.5 text-xs font-medium text-tema-azul">{{ $p['hora'] ?? 'Sin hora todavía' }}</span>
            @endif
            {{-- La pista, una vez y bien a la vista: es lo que se busca al llegar. --}}
            @if ($p['porJugar'] && $p['pista'] !== null)
                <span class="rounded-[3px] bg-tema-pizarra px-2.5 py-0.5 text-sm font-bold text-white">{{ $p['pista'] }}</span>
            @endif
        </div>
    </div>
    <div class="mt-1.5 text-lg font-semibold leading-snug">{{ ($enSuTorneo ?? false) ? $p['rival'] : $p['texto'] }}</div>
    @if ($p['anterior'] !== null)
        <p class="mt-0.5 text-xs text-tema-suave">{{ $p['anterior'] }}{{ str_starts_with($p['anterior'], 'Después') || str_starts_with($p['anterior'], 'Ahora') ? ' · estad pendientes de que acabe' : '' }}</p>
    @endif
    @if ($p['estado'] === \App\Enums\EstadoPartido::EnJuego && $p['enVivo'] !== '')
        <p class="mt-1 text-lg font-semibold tabular-nums">{{ $p['enVivo'] }}</p>
    @endif
    <div class="mt-1 flex flex-wrap items-center gap-2 text-sm">
        @if ($p['resultado'] !== '')
            <span class="text-base font-semibold tabular-nums">{{ $p['resultado'] }}</span>
            @if ($p['gane'] === true) <span class="rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-800">Ganado</span>
            @elseif ($p['gane'] === false) <span class="rounded-full bg-tema-fondo px-2 py-0.5 text-xs font-medium text-tema-suave">Perdido</span>
            @endif
        @else
            <span class="text-tema-suave">{{ $p['estado']->etiqueta() }}</span>
        @endif
    </div>
    @if ($editando === $p['id'])
        @include('livewire.publico._formulario-resultado', ['local' => $p['local'], 'visitante' => $p['visitante'], 'conEspeciales' => false])
    @elseif ($p['puedeMarcar'] && $p['estado'] === \App\Enums\EstadoPartido::EnJuego)
        @include('livewire.mesa._marcador', ['p' => $p + ['marcadorTexto' => $p['enVivo']]])
        <button type="button" wire:click="terminarPartido({{ $p['id'] }})" class="mt-3 boton px-4 py-1.5">Hemos terminado: guardar resultado</button>
    @elseif ($p['puedeAnotar'] || $p['puedeValidar'])
        <div class="mt-3 flex flex-wrap gap-2">
            @if ($p['puedeMarcar'])
                <button type="button" wire:click="empezarPartido({{ $p['id'] }})" class="boton px-4 py-1.5">Empezamos</button>
            @endif
            @if ($p['puedeAnotar'])
                <button type="button" wire:click="abrirResultado({{ $p['id'] }})" class="boton-claro px-4 py-1.5">Anotar resultado</button>
            @endif
            @if ($p['puedeValidar'])
                <button type="button" wire:click="validar({{ $p['id'] }})" class="boton px-4 py-1.5">Es correcto: validar</button>
            @endif
        </div>
    @endif
</li>
