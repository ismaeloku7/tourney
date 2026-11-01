{{-- El inicio del panel (App\Filament\Pages\Panel). Los estilos esc-* están en resources/css/filament/panel/theme.css. --}}
@php
    $donut = function (int $parte, int $total): string {
        $c = 2 * M_PI * 22;
        $hecho = $total > 0 ? min(1, $parte / $total) * $c : 0;

        return '<svg viewBox="0 0 56 56" class="size-14 -rotate-90"><circle cx="28" cy="28" r="22" fill="none" stroke-width="7"></circle><circle cx="28" cy="28" r="22" fill="none" stroke-width="7" stroke-dasharray="'.round($hecho, 2).' '.round($c, 2).'"></circle></svg>';
    };
@endphp

<x-filament-panels::page>
    <div class="esc">
        {{-- ── Organización ────────────────────────────────────────── --}}
        <div class="esc-titulo-ctn">
            <h2 class="esc-titulo">Organización <x-filament::icon icon="heroicon-m-chevron-down" class="size-4" /></h2>
        </div>

        <div class="esc-tarjeta esc-columnas">
            <div>
                <span class="esc-etiqueta">Torneos</span>
                <div class="esc-dato"><b class="esc-num">{{ $torneos['activos'] }}</b> Activos</div>
                <div class="esc-dato"><span class="esc-cuadro verde">{{ $torneos['enJuego'] }}</span> <a href="{{ $torneos['lista'] }}" class="esc-enlace">En juego</a></div>
                <div class="esc-dato"><span class="esc-cuadro naranja">{{ $torneos['abiertos'] }}</span> <a href="{{ $torneos['lista'] }}" class="esc-enlace">Con la inscripción abierta</a></div>
                <div class="esc-dato"><span class="esc-cuadro gris">{{ $torneos['borradores'] }}</span> <span class="esc-suave">En borrador</span></div>
                @if ($torneos['crear'])
                    <a href="{{ $torneos['crear'] }}" class="esc-enlace mt-1">Crear torneo</a>
                @endif
            </div>

            <div>
                @if ($proximo)
                    <span class="esc-etiqueta"><span class="font-normal text-tema-suave">Próximo:</span> {{ $proximo['nombre'] }}</span>
                    <div class="esc-dato">{{ $proximo['cuando'][0] }} <b class="text-tema-texto">{{ $proximo['cuando'][1] }}</b> {{ $proximo['cuando'][2] }}</div>
                    <div class="esc-dato">{{ ucfirst($proximo['fecha']) }}</div>
                    <div class="esc-dato esc-suave">{{ $proximo['estado'] }}</div>
                    @if ($proximo['pendientes'] > 0)
                        <div class="esc-dato"><span class="esc-cuadro amarillo">!</span> <span class="esc-suave">{{ $proximo['pendientes'] }} {{ $proximo['pendientes'] === 1 ? 'pareja pendiente' : 'parejas pendientes' }} de confirmar</span></div>
                    @endif
                    <div class="mt-1 flex gap-4">
                        <a href="{{ $proximo['parejas'] }}" class="esc-enlace">Ver parejas</a>
                        <a href="{{ $proximo['ficha'] }}" class="esc-enlace">Ficha del torneo</a>
                    </div>
                @else
                    <span class="esc-etiqueta">Próximo torneo</span>
                    <p class="esc-suave">No hay ninguno por delante.</p>
                @endif
            </div>

            <div>
                <span class="esc-etiqueta">Partidos</span>
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <div class="esc-grande">{{ $partidos['jugados'] }}<span class="text-lg text-tema-suave">/{{ $partidos['total'] }}</span></div>
                        <div class="esc-suave">jugados en los torneos activos</div>
                    </div>
                    <div class="esc-donut">{!! $donut($partidos['jugados'], $partidos['total']) !!}</div>
                </div>
                @if ($partidos['porValidar'] > 0)
                    <div class="esc-dato"><span class="esc-cuadro amarillo">{{ $partidos['porValidar'] }}</span> <span class="esc-suave">resultados sin validar</span></div>
                @endif
                @if ($partidos['mesa'])
                    <a href="{{ $partidos['mesa'] }}" class="esc-enlace mt-1">Mesa de partidos</a>
                @endif
            </div>
        </div>

        {{-- ── Competición ─────────────────────────────────────────── --}}
        <div class="esc-titulo-ctn mt-2">
            <h2 class="esc-titulo">Competición <x-filament::icon icon="heroicon-m-chevron-down" class="size-4" /></h2>
        </div>

        <div class="esc-rejilla">
            <div class="esc-pila">
                <div class="esc-tarjeta flex flex-col">
                    <div class="esc-cabecera">
                        <div class="esc-flechas"><button type="button" wire:click="diaAnterior" aria-label="Día anterior"><x-filament::icon icon="heroicon-m-chevron-left" class="size-4" /></button></div>
                        <span>{{ $dia['titulo'] }}</span>
                        <div class="esc-flechas"><button type="button" wire:click="diaSiguiente" aria-label="Día siguiente"><x-filament::icon icon="heroicon-m-chevron-right" class="size-4" /></button></div>
                    </div>
                    <div class="esc-cuerpo">
                        <div class="grid grid-cols-2 items-center gap-4 border-b border-tema-borde pb-4 text-center dark:border-white/10">
                            <div>
                                <div class="esc-suave">Partidos del día</div>
                                <div class="text-sm">{{ $dia['jugados'] }} jugados de {{ $dia['total'] }}</div>
                            </div>
                            <div>
                                <div class="esc-suave">Siguiente</div>
                                <div class="text-2xl font-light text-tema-azul">{{ $dia['siguiente'] ?? '—' }}</div>
                            </div>
                            <div>
                                @if ($proximo)
                                    <a href="{{ $proximo['ficha'] }}" class="esc-boton pizarra">Ficha del torneo</a>
                                @endif
                            </div>
                            <div>
                                @if ($partidos['mesa'])
                                    <a href="{{ $partidos['mesa'] }}" class="esc-boton verde">Mesa de partidos</a>
                                @endif
                            </div>
                        </div>

                        @if ($dia['lista'] === [])
                            <p class="esc-vacio">Ningún partido con hora este día.</p>
                        @else
                            <ul class="esc-lista">
                                @foreach ($dia['lista'] as $p)
                                    <li>
                                        <span class="esc-hora">{{ $p['hora'] }}</span>
                                        <div class="min-w-0 flex-1">
                                            <div class="truncate">{{ $p['local'] }} <span class="text-tema-suave">–</span> {{ $p['visitante'] }}</div>
                                            <div class="esc-suave truncate">{{ $p['pista'] }} · {{ $p['categoria'] }}</div>
                                        </div>
                                        @if ($p['resultado'])
                                            <span class="text-xs font-semibold tabular-nums">{{ $p['resultado'] }}</span>
                                        @else
                                            <x-filament::badge :color="$p['color']" size="sm">{{ $p['estado'] }}</x-filament::badge>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                            @if ($dia['mas'] > 0)
                                <p class="esc-suave pt-2">Y {{ $dia['mas'] }} más en la mesa de partidos.</p>
                            @endif
                        @endif
                    </div>
                </div>

                <div class="esc-tarjeta flex flex-col">
                    <div class="esc-cabecera"><span>{{ $mes['titulo'] }}</span></div>
                    <div class="esc-cuerpo flex flex-col gap-2">
                        <div class="esc-dato">Inscripciones nuevas <b>{{ $mes['inscripciones'] }}</b></div>
                        <div class="esc-dato">Parejas confirmadas <b>{{ $mes['confirmadas'] }}</b></div>
                        <div class="esc-dato">Partidos jugados <b>{{ $mes['partidos'] }}</b></div>
                        <div class="esc-dato">Jugadores nuevos <b>{{ $mes['jugadores'] }}</b></div>
                    </div>
                    <div class="esc-pie"><a href="{{ $mes['torneos'] }}" class="esc-enlace">Torneos</a></div>
                </div>
            </div>

            <div class="esc-pila">
                <div class="esc-tarjeta flex flex-col">
                    <div class="esc-cabecera">
                        <span class="flex items-center gap-2">Por revisar @if ($revision['total'] > 0)<span class="esc-contador">{{ $revision['total'] }}</span>@endif</span>
                        @if ($revision['paginas'] > 1)
                            <div class="esc-flechas">
                                <button type="button" wire:click="revisionAnterior" @disabled($revision['pagina'] === 0) aria-label="Anteriores"><x-filament::icon icon="heroicon-m-chevron-left" class="size-4" /></button>
                                <button type="button" wire:click="revisionSiguiente" @disabled($revision['pagina'] >= $revision['paginas'] - 1) aria-label="Siguientes"><x-filament::icon icon="heroicon-m-chevron-right" class="size-4" /></button>
                            </div>
                        @endif
                    </div>
                    <div class="esc-cuerpo">
                        @forelse ($revision['items'] as $item)
                            <div @class(['py-2', 'border-t border-tema-borde dark:border-white/10' => ! $loop->first])>
                                <p><span class="text-tema-azul">{{ $item['quien'] }}</span> {{ $item['texto'] }} <b>{{ $item['donde'] }}</b></p>
                                <p class="esc-suave">{{ $item['cuando'] }}</p>
                                <div class="mt-2 flex gap-5">
                                    @if ($item['tipo'] === 'inscripcion')
                                        <button type="button" wire:click="confirmarInscripcion({{ $item['id'] }})" class="esc-check"><span></span> Confirmar</button>
                                        <button type="button" wire:click="darDeBaja({{ $item['id'] }})" wire:confirm="¿Dar de baja a esta pareja?" class="esc-check"><span></span> Dar de baja</button>
                                    @else
                                        <button type="button" wire:click="validarResultado({{ $item['id'] }})" class="esc-check"><span></span> Validar</button>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p class="esc-vacio">Nada pendiente: ni parejas por confirmar ni resultados por validar.</p>
                        @endforelse
                    </div>
                </div>

                <div class="esc-tarjeta flex flex-col">
                    <div class="esc-cabecera"><span>Actividad</span></div>
                    <div class="esc-cuerpo">
                        @forelse ($actividad as $linea)
                            <p @class(['py-1.5 flex justify-between gap-3', 'border-t border-tema-borde dark:border-white/10' => ! $loop->first])>
                                <span>{{ $linea['texto'] }}</span>
                                <span class="esc-suave shrink-0">{{ $linea['cuando'] }}</span>
                            </p>
                        @empty
                            <p class="esc-vacio">Aún no hay actividad.</p>
                        @endforelse
                    </div>
                </div>

                <div class="esc-tarjeta grid sm:grid-cols-2 sm:divide-x divide-tema-borde dark:divide-white/10">
                    <div class="flex flex-col">
                        <div class="esc-cabecera"><span>Plazas</span></div>
                        <div class="esc-cuerpo flex items-center justify-between gap-3">
                            <div>
                                @if ($plazas['cupo'] > 0)
                                    <div><b class="text-base">{{ $plazas['ocupadas'] }}/{{ $plazas['cupo'] }}</b> <span class="esc-suave">ocupadas</span></div>
                                @else
                                    <div><b class="text-base">{{ $plazas['sinCupo'] }}</b> <span class="esc-suave">parejas confirmadas</span></div>
                                @endif
                                @if ($plazas['espera'] > 0)
                                    <div class="esc-suave">{{ $plazas['espera'] }} en lista de espera</div>
                                @endif
                            </div>
                            @if ($plazas['cupo'] > 0)
                                <div class="esc-donut">{!! $donut($plazas['ocupadas'], $plazas['cupo']) !!}</div>
                            @endif
                        </div>
                        @if ($proximo)
                            <div class="esc-pie"><a href="{{ $proximo['parejas'] }}" class="esc-enlace">+ Ver parejas</a></div>
                        @endif
                    </div>
                    <div class="flex flex-col">
                        <div class="esc-cabecera"><span>Próximo torneo</span></div>
                        <div class="esc-cuerpo">
                            @if ($proximo)
                                <div>{{ $proximo['cuando'][0] }} <b>{{ $proximo['cuando'][1] }}</b> {{ $proximo['cuando'][2] }}</div>
                                <div class="esc-suave">{{ ucfirst($proximo['fecha']) }}</div>
                            @else
                                <div class="esc-suave">Ninguno por delante.</div>
                            @endif
                        </div>
                        @if ($proximo)
                            <div class="esc-pie"><a href="{{ $proximo['ficha'] }}" class="esc-enlace">Ver {{ $proximo['nombre'] }}</a></div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
