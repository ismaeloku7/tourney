{{-- Un cuadro por rondas (hecho o previsto). Espera $bloque (de DatosDelTorneo::cuadros() o cuadrosPrevistos()). --}}
    <section class="tarjeta p-4" wire:key="cuadro-{{ $bloque['fase']->id }}">
        <h2 class="text-xl font-semibold">{{ $bloque['fase']->nombre }}@if ($bloque['previsto'] ?? false) <span class="text-sm font-normal text-tema-suave">· previsto</span>@endif</h2>
        @if ($bloque['previsto'] ?? false)
            <p class="mt-1 text-xs text-tema-suave">Así quedaría el cuadro si los grupos acabasen ahora. Cambia con cada resultado.</p>
        @endif
        <div class="mt-3 flex gap-4 overflow-x-auto pb-2">
            @foreach ($bloque['rondas'] as $ronda)
                <div class="flex min-w-56 flex-1 flex-col justify-around gap-3">
                    <h3 class="text-center text-sm font-semibold">{{ $ronda['nombre'] }}</h3>
                    @foreach ($ronda['partidos'] as $p)
                        <div class="rounded-[3px] bg-tema-fondo p-3 text-sm">
                            @if (filled($p['codigo'] ?? null))<div class="text-xs font-semibold text-tema-suave">{{ $p['codigo'] }}</div>@endif
                            <div>{{ $p['partido'] }}</div>
                            <div class="mt-1 text-tema-suave">{{ $p['resultado'] ?: ($p['hora'] ?? '') }}</div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    </section>
