{{-- La ficha pública del torneo. En juego, se refresca cada 15 segundos. --}}
<div class="mx-auto max-w-5xl px-4 py-8" @if ($enJuego) wire:poll.15s @endif>
    <h1 class="text-2xl font-semibold sm:text-3xl">{{ $torneo->nombre }}</h1>
    <p class="mt-1 text-neutral-600">
        {{ $torneo->fecha_inicio->translatedFormat('l j \d\e F') }}@if (! $torneo->fecha_fin->isSameDay($torneo->fecha_inicio)) – {{ $torneo->fecha_fin->translatedFormat('l j \d\e F') }}@endif
        @if ($torneo->sede) · {{ $torneo->sede->nombre }} @endif
        · <span class="font-medium">{{ $torneo->estado->etiqueta() }}</span>
    </p>

    <nav class="mt-6 flex gap-1 overflow-x-auto border-b border-neutral-200" aria-label="Secciones del torneo">
        @foreach (\App\Livewire\Publico\FichaDelTorneo::PESTANAS as $clave => $nombre)
            <button type="button" wire:click="ver('{{ $clave }}')" @class(['whitespace-nowrap border-b-2 px-3 py-2 text-sm font-medium', 'border-(--acento) text-neutral-900' => $pestana === $clave, 'border-transparent text-neutral-500 hover:text-neutral-800' => $pestana !== $clave])>{{ $nombre }}</button>
        @endforeach
    </nav>

    <div class="mt-6">
        @if ($pestana === 'info')
            <div class="grid gap-6 md:grid-cols-[2fr_1fr]">
                <div>
                    @if ($torneo->normativa)
                        <h2 class="font-semibold">Normativa</h2>
                        <div class="mt-2 whitespace-pre-line text-neutral-800">{{ $torneo->normativa }}</div>
                    @endif
                    @if ($torneo->direccion || $torneo->sede?->direccion)
                        <h2 class="mt-6 font-semibold">Dónde</h2>
                        <p class="mt-2 text-neutral-800">{{ $torneo->direccion ?? $torneo->sede?->direccion }}</p>
                    @endif
                    @if ($torneo->contacto)
                        <h2 class="mt-6 font-semibold">Contacto</h2>
                        <p class="mt-2 text-neutral-800">{{ $torneo->contacto }}</p>
                    @endif
                </div>
                <div class="rounded-xl border border-neutral-200 bg-white p-5">
                    <h2 class="font-semibold">Categorías</h2>
                    <ul class="mt-3 space-y-2">
                        @foreach ($datos->inscritos() as $fila)
                            <li class="flex justify-between gap-3 text-sm">
                                <span>{{ $fila['categoria']->nombre }}</span>
                                <span class="text-neutral-500">
                                    @if ($fila['plazas'] === null) {{ count($fila['confirmadas']) }} parejas
                                    @elseif ($fila['plazas'] === 0) Completa
                                    @else {{ $fila['plazas'] }} plazas libres
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                    @if ($torneo->inscripcionAbierta())
                        <p class="mt-4 text-sm text-neutral-600">La inscripción está abierta. Escribe a la organización para apuntarte.</p>
                    @endif
                </div>
            </div>
        @elseif ($pestana === 'inscritos')
            <div class="grid gap-6 sm:grid-cols-2">
                @forelse ($contenido as $fila)
                    <section class="rounded-xl border border-neutral-200 bg-white p-5">
                        <h2 class="font-semibold">{{ $fila['categoria']->nombre }}</h2>
                        <ol class="mt-3 list-decimal space-y-1 pl-5 text-sm">
                            @forelse ($fila['confirmadas'] as $nombre)
                                <li>{{ $nombre }}</li>
                            @empty
                                <li class="list-none text-neutral-500">Sin parejas todavía.</li>
                            @endforelse
                        </ol>
                        @if ($fila['espera'] > 0)
                            <p class="mt-3 text-sm text-neutral-500">{{ $fila['espera'] }} en lista de espera.</p>
                        @endif
                    </section>
                @empty
                    <p class="text-neutral-600">El torneo aún no tiene categorías.</p>
                @endforelse
            </div>
        @elseif ($pestana === 'grupos')
            @forelse ($contenido as $bloque)
                <h2 class="mt-6 text-lg font-semibold first:mt-0">{{ $bloque['categoria']->nombre }} · {{ $bloque['fase']->nombre }}</h2>
                <div class="mt-3 grid gap-4 lg:grid-cols-2">
                    @foreach ($bloque['grupos'] as $g)
                        <section class="rounded-xl border border-neutral-200 bg-white p-4">
                            <h3 class="font-semibold">Grupo {{ $g['grupo']->nombre }}</h3>
                            <table class="mt-2 w-full text-sm">
                                <thead class="text-left text-neutral-500"><tr><th class="py-1">#</th><th>Pareja</th><th class="text-right">Pts</th><th class="text-right">PJ</th><th class="text-right">Sets</th><th class="text-right">Juegos</th></tr></thead>
                                <tbody>
                                    @foreach ($g['lineas'] as $l)
                                        <tr @class(['border-t border-neutral-100', 'font-semibold' => $l['clasificado']])>
                                            <td class="py-1">{{ $l['posicion'] }}</td><td>{{ $l['nombre'] }}</td><td class="text-right">{{ $l['puntos'] }}</td><td class="text-right">{{ $l['pj'] }}</td><td class="text-right">{{ $l['sets'] }}</td><td class="text-right">{{ $l['juegos'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <ul class="mt-3 space-y-1 text-sm">
                                @foreach ($g['partidos'] as $p)
                                    <li class="flex justify-between gap-3"><span>{{ $p['partido'] }}</span><span class="whitespace-nowrap text-neutral-500">{{ $p['resultado'] ?: $p['hora'] }}</span></li>
                                @endforeach
                            </ul>
                        </section>
                    @endforeach
                </div>
            @empty
                <p class="text-neutral-600">Los grupos salen cuando la organización hace el sorteo.</p>
            @endforelse
        @elseif ($pestana === 'cuadro')
            @forelse ($contenido as $bloque)
                <h2 class="mt-6 text-lg font-semibold first:mt-0">{{ $bloque['categoria']->nombre }} · {{ $bloque['fase']->nombre }}</h2>
                <div class="mt-3 flex gap-4 overflow-x-auto pb-2">
                    @foreach ($bloque['rondas'] as $ronda)
                        <div class="flex min-w-60 flex-1 flex-col justify-around gap-3">
                            <h3 class="text-center text-sm font-semibold">{{ $ronda['nombre'] }}</h3>
                            @foreach ($ronda['partidos'] as $p)
                                <div class="rounded-lg border border-neutral-200 bg-white p-3 text-sm">
                                    <div>{{ $p['partido'] }}</div>
                                    <div class="mt-1 text-neutral-500">{{ $p['resultado'] ?: ($p['hora'] ?? '') }}</div>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            @empty
                <p class="text-neutral-600">El cuadro se llena cuando termina la fase anterior.</p>
            @endforelse
        @elseif ($pestana === 'horarios')
            @forelse ($contenido as $dia => $partidos)
                <h2 class="mt-6 text-lg font-semibold first:mt-0">{{ ucfirst($dia) }}</h2>
                <div class="mt-3 overflow-x-auto rounded-xl border border-neutral-200 bg-white">
                    <table class="w-full text-sm">
                        <thead class="text-left text-neutral-500"><tr><th class="px-3 py-2">Hora</th><th>Pista</th><th>Categoría</th><th>Partido</th><th>Resultado</th></tr></thead>
                        <tbody>
                            @foreach ($partidos as $p)
                                <tr class="border-t border-neutral-100"><td class="px-3 py-2">{{ $p['hora'] }}</td><td>{{ $p['pista'] }}</td><td>{{ $p['categoria'] }}</td><td>{{ $p['partido'] }}</td><td>{{ $p['resultado'] }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @empty
                <p class="text-neutral-600">Los horarios se publican cuando el torneo empieza.</p>
            @endforelse
        @endif
    </div>
</div>
