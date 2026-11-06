{{--
    «Mi torneo» en el portal del jugador: arriba el torneo y su equipo, su
    próximo partido en grande, sus partidos, su grupo, el cuadro, los demás
    grupos y categorías, y al final cuándo no puede jugar y la baja. Solo
    nombres de equipo; los niveles se cambian en «Mis datos».
--}}
<div class="space-y-8" @if ($torneo->estado === \App\Enums\EstadoTorneo::EnJuego) wire:poll.30s @endif>
    <a href="{{ route('jugador.portal') }}" class="enlace text-sm">← Inicio</a>

    <header class="overflow-hidden rounded-[3px] bg-tema-pizarra text-white">
        <div class="px-5 py-6 sm:px-7 sm:py-8">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-white/70">
                {{ $inscripcion->categoria->nombre }} ·
                {{ $torneo->fecha_inicio->isSameDay($torneo->fecha_fin) ? $torneo->fecha_inicio->translatedFormat('j \d\e F') : $torneo->fecha_inicio->translatedFormat('j').'–'.$torneo->fecha_fin->translatedFormat('j \d\e F') }}
                @if ($torneo->sede) · {{ $torneo->sede->nombre }} @endif
            </p>
            <h1 class="mt-2 text-3xl font-semibold leading-tight sm:text-4xl">{{ $torneo->nombre }}</h1>
            <div class="mt-4 flex flex-wrap items-center gap-2">
                <span class="rounded-full bg-white/15 px-3 py-1 text-sm">Tu equipo: <strong class="font-semibold">{{ $equipo }}</strong></span>
                <span class="rounded-full bg-white/15 px-3 py-1 text-sm">{{ $torneo->estado->etiqueta() }}</span>
                @include('livewire.jugador._estado-inscripcion', ['inscripcion' => $inscripcion])
            </div>
        </div>
    </header>

    @include('livewire.jugador._aviso')

    @if ($proximo !== null)
        <section class="tarjeta border-l-4 border-tema-naranja p-5 sm:p-6">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-tema-naranja">Tu próximo partido</p>
            <p class="mt-2 text-sm text-tema-suave">contra</p>
            <p class="text-2xl font-semibold leading-tight text-tema-texto sm:text-3xl">{{ $proximo['rival'] }}</p>
            <div class="mt-3 flex flex-wrap gap-2 text-sm">
                <span class="rounded-full bg-tema-fondo px-3 py-1 font-medium">{{ $proximo['hora'] ?? 'Sin hora todavía' }}</span>
                @if ($proximo['pista'])<span class="rounded-full bg-tema-fondo px-3 py-1 font-medium">{{ $proximo['pista'] }}</span>@endif
                @if ($proximo['estado'] === \App\Enums\EstadoPartido::EnJuego)<span class="rounded-full bg-green-50 px-3 py-1 font-medium text-green-800">En juego{{ $proximo['enVivo'] !== '' ? ' · '.$proximo['enVivo'] : '' }}</span>@endif
            </div>
            @if ($proximo['anterior'] !== null)
                <p class="mt-2 text-sm text-tema-suave">{{ $proximo['anterior'] }}</p>
            @endif
        </section>
    @endif

    <section>
        <div class="flex items-baseline justify-between gap-3">
            <h2 class="text-xl font-semibold">Por jugar</h2>
            @if ($porJugar !== [])<span class="text-sm text-tema-suave">{{ count($porJugar) }} {{ count($porJugar) === 1 ? 'partido' : 'partidos' }}</span>@endif
        </div>
        <ul class="mt-3 grid gap-3 sm:grid-cols-2">
            @forelse ($porJugar as $p)
                @include('livewire.jugador._partido', ['p' => $p, 'enSuTorneo' => true])
            @empty
                <li class="text-sm text-tema-suave">{{ $jugados === [] ? 'Tus partidos salen aquí cuando la organización hace el sorteo.' : 'No te queda ninguno por ahora.' }}</li>
            @endforelse
        </ul>
    </section>

    @if ($jugados !== [])
        <section>
            <h2 class="text-xl font-semibold">Jugados</h2>
            <ul class="mt-3 grid gap-3 sm:grid-cols-2">
                @foreach ($jugados as $p)
                    @include('livewire.jugador._partido', ['p' => $p, 'enSuTorneo' => true])
                @endforeach
            </ul>
        </section>
    @endif

    @if ($miGrupo !== null)
        <section class="tarjeta p-5 sm:p-6">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="text-xl font-semibold">Mi grupo · {{ $miGrupo['grupo']->nombre }}</h2>
                <span class="text-sm text-tema-suave">{{ $miGrupo['fase']->nombre }}</span>
            </div>
            <div class="mt-4">
                @include('livewire.jugador._grupo', ['g' => $miGrupo, 'miParticipante' => $inscripcion->participante_id])
            </div>
            @if ($miGrupo['cruces'])
                <p class="mt-3 text-xs text-tema-suave">Cruce: la primera ronda del cuadro si la clasificación acabase así. Cambia con cada resultado.</p>
            @endif
        </section>
    @endif

    @foreach ($cuadrosQueFaltan as $pendiente)
        <section class="tarjeta p-4" wire:key="cuadro-pendiente-{{ $pendiente['fase']->id }}">
            <h2 class="text-base font-semibold">{{ $pendiente['fase']->nombre }}</h2>
            <p class="mt-1 text-sm text-tema-suave">Todavía no hay cuadro. {{ $pendiente['motivo'] }}</p>
        </section>
    @endforeach

    @foreach ($cuadros as $bloque)
        @include('livewire.jugador._cuadro', ['bloque' => $bloque])
    @endforeach

    @if ($faseOculta)
        <section class="tarjeta p-4 text-sm text-tema-suave">La organización todavía no ha publicado los grupos ni el cuadro de tu categoría. En cuanto lo haga, salen aquí.</section>
    @endif

    @if ($otrosGrupos !== [])
        <section>
            <h2 class="text-xl font-semibold">Los otros grupos de {{ $inscripcion->categoria->nombre }}</h2>
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


    @if ($otrasCategorias !== [])
        <section>
            <h2 class="text-xl font-semibold">Otras categorías</h2>
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach ($otrasCategorias as $id => $nombre)
                    <button type="button" wire:click="$set('verCategoria', {{ $id }})" @class(['rounded-full px-3.5 py-1.5 text-sm font-medium transition', 'bg-tema-texto text-white' => $verCategoria === $id, 'bg-tema-fondo text-tema-texto hover:bg-tema-borde' => $verCategoria !== $id])>{{ $nombre }}</button>
                @endforeach
            </div>
            @if ($explorar !== null)
                <div class="mt-3 space-y-2" wire:key="explorar-{{ $verCategoria }}">
                    @foreach ($explorar['grupos'] as $g)
                        <details class="tarjeta p-4" wire:key="explorar-grupo-{{ $g['grupo']->id }}" @if ($loop->first) open @endif>
                            <summary class="cursor-pointer font-medium">Grupo {{ $g['grupo']->nombre }} <span class="text-sm font-normal text-tema-suave">· {{ $g['fase']->nombre }}</span></summary>
                            <div class="mt-3">
                                @include('livewire.jugador._grupo', ['g' => $g, 'miParticipante' => 0])
                            </div>
                        </details>
                    @endforeach
                    @foreach ($explorar['cuadros'] as $bloque)
                        @include('livewire.jugador._cuadro', ['bloque' => $bloque])
                    @endforeach
                </div>
            @endif
        </section>
    @endif
    <div class="grid gap-6 lg:grid-cols-2">
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
            <a href="{{ route('jugador.datos') }}" class="enlace block">Tu nivel y el de tu compañero, en «Mis datos» →</a>
            @if ($puedeDarseDeBaja)
                <button type="button" wire:click="darmeDeBaja" wire:confirm="¿Seguro? Tu pareja deja la categoría." class="text-red-700 underline">Darme de baja de este torneo</button>
            @endif
        </section>
    </div>
</div>
