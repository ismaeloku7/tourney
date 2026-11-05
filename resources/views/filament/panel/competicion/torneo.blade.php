{{--
    La competición del torneo (CompeticionDelTorneo): un selector de
    categoría y, por cada fase, los grupos en tarjetas (clasificación con
    el cruce previsto y sus partidos) o el cuadro por rondas. Cada partido
    lleva sus acciones; cada fase, su cierre.
--}}
@php
    use App\Enums\EstadoPartido;
    use App\Filament\Competicion\Marcador;
@endphp
<x-filament-panels::page>
    @if ($categorias->isEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400">El torneo aún no tiene categorías.</p>
    @else
        <div class="flex flex-wrap items-center gap-2" role="tablist" aria-label="Categorías">
            @foreach ($categorias as $c)
                <button type="button" role="tab" wire:click="elegirCategoria({{ $c->id }})" aria-selected="{{ $categoria?->id === $c->id ? 'true' : 'false' }}"
                    @class(['rounded-full px-4 py-1.5 text-sm font-medium transition', 'bg-primary-600 text-white' => $categoria?->id === $c->id, 'bg-white text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-200 dark:ring-white/10' => $categoria?->id !== $c->id])>{{ $c->nombre }}</button>
            @endforeach
            @if ($configurar)
                <a href="{{ $configurar }}" class="ms-auto text-sm text-primary-600 hover:underline dark:text-primary-400">Configurar la categoría y sortear</a>
            @endif
        </div>

        @if ($fases === [])
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $categoria?->nombre }} no tiene fases todavía: se crean al configurar la categoría.</p>
        @endif

        @foreach ($fases as $bloque)
            @php($fase = $bloque['fase'])
            <section class="space-y-4" wire:key="fase-{{ $fase->id }}">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold">{{ $fase->nombre }}</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            <x-filament::badge :color="$fase->estado->color()" size="sm">{{ $fase->estado->etiqueta() }}</x-filament::badge>
                            @if ($bloque['total'] > 0) · {{ $bloque['jugados'] }} de {{ $bloque['total'] }} partidos jugados @endif
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        {{ ($this->cerrarFaseAction)(['fase' => $fase->id]) }}
                    </div>
                </div>

                @if ($fase->tipo->esDeGrupos() && $bloque['grupos'] === [])
                    <p class="text-sm text-gray-500 dark:text-gray-400">Sin sortear. <a href="{{ $configurar }}" class="text-primary-600 hover:underline dark:text-primary-400">Sortear los grupos desde la categoría</a>.</p>
                @endif

                @if ($bloque['grupos'] !== [])
                    @php($conCruces = $bloque['grupos'][0]['cruces'] !== [])
                    <div class="grid gap-4 xl:grid-cols-2">
                        @foreach ($bloque['grupos'] as $g)
                            @php($jugadosDelGrupo = $g['partidos']->filter(fn ($p) => $p->cerrado())->count())
                            <div class="esc-tarjeta p-4" wire:key="grupo-{{ $g['grupo']->id }}">
                                <div class="flex items-baseline justify-between gap-3">
                                    <h3 class="font-semibold">Grupo {{ $g['grupo']->nombre }}</h3>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $jugadosDelGrupo }}/{{ $g['partidos']->count() }} jugados</span>
                                </div>
                                @if ($g['lineas']->isEmpty())
                                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Sin partidos todavía.</p>
                                @else
                                    <table class="mt-2 w-full text-sm">
                                        <thead class="text-left text-xs text-gray-500 dark:text-gray-400">
                                            <tr><th class="py-1 pe-2 text-right font-medium">#</th><th class="font-medium">Pareja</th><th class="text-right font-medium">Pts</th><th class="text-right font-medium">PJ</th><th class="text-right font-medium">Sets</th><th class="text-right font-medium">Juegos</th>@if ($conCruces)<th class="text-right font-medium">Cruce previsto</th>@endif</tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($g['lineas'] as $linea)
                                                <tr @class(['border-t border-gray-100 dark:border-white/5', 'font-semibold' => $linea->clasificado_como !== null])>
                                                    <td class="py-1 pe-2 text-right tabular-nums">{{ $linea->posicion }}</td>
                                                    <td>{{ $linea->participante->nombre }}@if ($linea->clasificado_como !== null) <span class="text-xs font-normal text-gray-500">{{ $linea->clasificado_como }}</span>@endif</td>
                                                    <td class="text-right tabular-nums">{{ $linea->puntos }}</td>
                                                    <td class="text-right tabular-nums">{{ $linea->pj }}</td>
                                                    <td class="text-right tabular-nums">{{ $linea->sets_favor }}-{{ $linea->sets_contra }}</td>
                                                    <td class="text-right tabular-nums">{{ $linea->juegos_favor }}-{{ $linea->juegos_contra }}</td>
                                                    @if ($conCruces)<td class="whitespace-nowrap text-right text-gray-500 dark:text-gray-400">@isset ($g['cruces'][$linea->participante_id]) vs {{ $g['cruces'][$linea->participante_id]->etiqueta() }} @endisset</td>@endif
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                @endif

                                <ul class="mt-3 divide-y divide-gray-100 border-t border-gray-100 text-sm dark:divide-white/5 dark:border-white/5">
                                    @foreach ($g['partidos'] as $p)
                                        <li class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 py-2" wire:key="partido-{{ $p->id }}">
                                            <div class="min-w-0">
                                                <span class="font-medium">{{ Marcador::lado($p, true) }} – {{ Marcador::lado($p, false) }}</span>
                                                <span class="block text-xs text-gray-500 dark:text-gray-400">
                                                    @if ($p->jornada) Jornada {{ $p->jornada }} @endif
                                                    @if ($p->franja) · {{ $p->franja->inicio->translatedFormat('D j/n H:i') }}@if ($p->pista) · {{ $p->pista->nombre }}@endif @endif
                                                </span>
                                            </div>
                                            <div class="flex items-center gap-3">
                                                @if ($p->cerrado() || $p->estado === EstadoPartido::PendienteValidacion)
                                                    <span class="tabular-nums font-medium">{{ Marcador::resultado($p) }}</span>
                                                @endif
                                                <x-filament::badge :color="$p->estado->color()" size="sm">{{ $p->estado->etiqueta() }}</x-filament::badge>
                                                {{ ($this->resultadoAction)(['partido' => $p->id]) }}
                                                {{ ($this->validarAction)(['partido' => $p->id]) }}
                                                {{ ($this->anularAction)(['partido' => $p->id]) }}
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                    @if ($conCruces)
                        <p class="text-xs text-gray-500 dark:text-gray-400">Cruce previsto: la primera ronda del cuadro si la clasificación acabase así; cambia con cada resultado.</p>
                    @endif
                @endif

                @if ($fase->tipo->esDeCuadro())
                    @if ($bloque['rondas'] === [])
                        <p class="text-sm text-gray-500 dark:text-gray-400">El cuadro se llena al cerrar la fase anterior.</p>
                    @else
                        <div class="esc-tarjeta overflow-x-auto p-4">
                            <div class="flex gap-6">
                                @foreach ($bloque['rondas'] as $ronda)
                                    <div class="flex min-w-64 flex-1 flex-col justify-around gap-3" wire:key="ronda-{{ $fase->id }}-{{ $loop->index }}">
                                        <h3 class="text-center text-sm font-semibold">{{ $ronda['nombre'] }}</h3>
                                        @foreach ($ronda['partidos'] as $p)
                                            <div class="rounded-[3px] bg-gray-50 p-3 text-sm ring-1 ring-gray-100 dark:bg-white/5 dark:ring-white/10" wire:key="partido-{{ $p->id }}">
                                                <div @class(['flex justify-between gap-2', 'font-semibold' => $p->ganador_id !== null && $p->ganador_id === $p->local_id])><span class="truncate">{{ Marcador::lado($p, true) }}</span></div>
                                                <div @class(['flex justify-between gap-2', 'font-semibold' => $p->ganador_id !== null && $p->ganador_id === $p->visitante_id])><span class="truncate">{{ Marcador::lado($p, false) }}</span></div>
                                                <div class="mt-1 flex flex-wrap items-center justify-between gap-2 text-xs text-gray-500 dark:text-gray-400">
                                                    <span>
                                                        @if ($p->cerrado() || $p->estado === EstadoPartido::PendienteValidacion) <span class="tabular-nums font-medium text-gray-900 dark:text-white">{{ Marcador::resultado($p) }}</span>
                                                        @elseif ($p->franja) {{ $p->franja->inicio->translatedFormat('D j/n H:i') }}@if ($p->pista) · {{ $p->pista->nombre }}@endif
                                                        @else {{ $p->estado->etiqueta() }}
                                                        @endif
                                                    </span>
                                                    <span class="flex items-center gap-2">
                                                        {{ ($this->resultadoAction)(['partido' => $p->id]) }}
                                                        {{ ($this->validarAction)(['partido' => $p->id]) }}
                                                        {{ ($this->anularAction)(['partido' => $p->id]) }}
                                                    </span>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endif
            </section>
        @endforeach
    @endif
</x-filament-panels::page>
