<div class="space-y-6" @if ($torneo->estado === \App\Enums\EstadoTorneo::EnJuego) wire:poll.30s @endif>
    <a href="{{ route('jugador.portal') }}" class="enlace text-sm">← Inicio</a>

    <header class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold">{{ $torneo->nombre }}</h1>
            <p class="mt-1 text-sm text-tema-suave">
                {{ $inscripcion->categoria->nombre }} ·
                {{ $torneo->fecha_inicio->isSameDay($torneo->fecha_fin) ? $torneo->fecha_inicio->translatedFormat('j \d\e F') : $torneo->fecha_inicio->translatedFormat('j').'–'.$torneo->fecha_fin->translatedFormat('j \d\e F') }}
                @if ($torneo->sede) · {{ $torneo->sede->nombre }} @endif
                · {{ $torneo->estado->etiqueta() }}
            </p>
        </div>
        @include('livewire.jugador._estado-inscripcion', ['inscripcion' => $inscripcion])
    </header>

    @include('livewire.jugador._aviso')

    <div class="grid gap-6 lg:grid-cols-[3fr_2fr]">
        <div class="space-y-6">
            <section>
                <h2 class="text-base font-semibold">Mis partidos</h2>
                <ul class="mt-3 grid gap-3 sm:grid-cols-2">
                    @forelse ($partidos as $p)
                        @include('livewire.jugador._partido', ['p' => $p])
                    @empty
                        <li class="text-sm text-tema-suave">Tus partidos salen aquí cuando la organización hace el sorteo.</li>
                    @endforelse
                </ul>
            </section>

            @if ($miGrupo !== null)
                <section class="tarjeta p-4">
                    <h2 class="text-base font-semibold">Mi grupo: {{ $miGrupo['grupo']->nombre }} <span class="text-sm font-normal text-tema-suave">· {{ $miGrupo['fase']->nombre }}</span></h2>
                    <div class="mt-3">
                        @include('livewire.jugador._grupo', ['g' => $miGrupo, 'miParticipante' => $inscripcion->participante_id])
                    </div>
                    @if ($miGrupo['cruces'])
                        <p class="mt-2 text-xs text-tema-suave">Cruce: la primera ronda del cuadro si la clasificación acabase así. Cambia con cada resultado.</p>
                    @endif
                </section>
            @endif

            @if ($otrosGrupos !== [])
                <section>
                    <h2 class="text-base font-semibold">Los otros grupos de {{ $inscripcion->categoria->nombre }}</h2>
                    <div class="mt-3 space-y-2">
                        @foreach ($otrosGrupos as $g)
                            <details class="tarjeta p-4" wire:key="grupo-{{ $g['grupo']->id }}">
                                <summary class="cursor-pointer font-medium">Grupo {{ $g['grupo']->nombre }}</summary>
                                <div class="mt-3">
                                    @include('livewire.jugador._grupo', ['g' => $g, 'miParticipante' => $inscripcion->participante_id])
                                </div>
                            </details>
                        @endforeach
                    </div>
                </section>
            @endif

            @foreach ($cuadros as $bloque)
                <section class="tarjeta p-4" wire:key="cuadro-{{ $bloque['fase']->id }}">
                    <h2 class="text-base font-semibold">{{ $bloque['fase']->nombre }}</h2>
                    <div class="mt-3 flex gap-4 overflow-x-auto pb-2">
                        @foreach ($bloque['rondas'] as $ronda)
                            <div class="flex min-w-56 flex-1 flex-col justify-around gap-3">
                                <h3 class="text-center text-sm font-semibold">{{ $ronda['nombre'] }}</h3>
                                @foreach ($ronda['partidos'] as $p)
                                    <div class="rounded-[3px] bg-tema-fondo p-3 text-sm">
                                        <div>{{ $p['partido'] }}</div>
                                        <div class="mt-1 text-tema-suave">{{ $p['resultado'] ?: ($p['hora'] ?? '') }}</div>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>

        <div class="space-y-6">
            <section class="tarjeta p-4">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h2 class="text-base font-semibold">Tu pareja</h2>
                    @if ($media)<span class="text-sm text-tema-suave">{{ $media }}</span>@endif
                </div>
                <p class="mt-1 text-sm text-tema-suave">{{ $inscripcion->participante->nombre }}</p>

                <form wire:submit="guardarNiveles" class="mt-4 space-y-3">
                    @foreach ($jugadores as $jugador)
                        <div class="flex flex-wrap items-end justify-between gap-3 border-t border-tema-borde pt-3 first:border-t-0 first:pt-0" wire:key="jugador-{{ $jugador->id }}">
                            <div class="min-w-0">
                                <div class="text-sm font-medium">{{ $jugador->nombre_completo }} @if ($jugador->is($yo))<span class="text-tema-suave">(tú)</span>@endif</div>
                                @if ($loop->first)<div class="text-xs text-tema-suave">Capitán</div>@endif
                            </div>
                            <label class="text-sm">Nivel
                                <input type="text" inputmode="decimal" wire:model="niveles.{{ $jugador->id }}" placeholder="3 o 2,5" @disabled(! $editable) class="campo mt-1 block w-24 px-2 py-1 disabled:bg-tema-fondo">
                            </label>
                        </div>
                        @error('niveles.'.$jugador->id) <p class="text-sm text-red-700">{{ $message }}</p> @enderror
                    @endforeach

                    @if ($editable)
                        <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
                            <p class="text-xs text-tema-suave">Del 0 al 10. Cualquiera de los dos puede poner el nivel de ambos.</p>
                            <button type="submit" class="boton px-4 py-1.5">Guardar niveles</button>
                        </div>
                    @else
                        <p class="pt-2 text-xs text-tema-suave">El torneo ha terminado o la pareja está de baja: los niveles ya no se cambian desde aquí.</p>
                    @endif
                </form>
            </section>

            @if ($admiteDisponibilidad)
                <section class="tarjeta p-4" id="disponibilidad">
                    <h2 class="text-base font-semibold">Cuándo no puedo jugar</h2>
                    <p class="mt-1 text-sm text-tema-suave">Antes de que salgan los horarios, dinos qué ratos no puedes. Se tienen en cuenta al planificar.</p>
                    <ul class="mt-2 space-y-1 text-sm">
                        @foreach ($noDisponibilidades as $hueco)
                            <li class="flex justify-between gap-3" wire:key="hueco-{{ $hueco->id }}">
                                <span>{{ $hueco->desde->translatedFormat('l j') }}, de {{ $hueco->desde->format('H:i') }} a {{ $hueco->hasta->format('H:i') }}</span>
                                <button type="button" wire:click="quitarNoDisponible({{ $hueco->id }})" class="text-red-700 underline">Quitar</button>
                            </li>
                        @endforeach
                    </ul>
                    <form wire:submit="anotarNoDisponible" class="mt-3 flex flex-wrap items-end gap-2 text-sm">
                        <label>Día
                            <select wire:model="noDisponible.dia" class="mt-1 block campo px-2 py-1">
                                <option value="">—</option>
                                @foreach ($torneo->fecha_inicio->toPeriod($torneo->fecha_fin) as $dia)
                                    <option value="{{ $dia->toDateString() }}">{{ $dia->translatedFormat('l j') }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Desde <input type="time" wire:model="noDisponible.desde" class="mt-1 block campo px-2 py-1"></label>
                        <label>Hasta <input type="time" wire:model="noDisponible.hasta" class="mt-1 block campo px-2 py-1"></label>
                        <button type="submit" class="boton px-4 py-1.5">Añadir</button>
                    </form>
                </section>
            @endif

            <section class="space-y-2 text-sm">
                <a href="{{ route('torneo.ficha', $torneo) }}" class="enlace block">Ver la web del torneo: horarios, todos los grupos y resultados →</a>
                @if ($puedeDarseDeBaja)
                    <button type="button" wire:click="darmeDeBaja" wire:confirm="¿Seguro? Tu pareja deja la categoría." class="text-red-700 underline">Darme de baja de este torneo</button>
                @endif
            </section>
        </div>
    </div>
</div>
