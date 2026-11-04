@php($porValidar = collect($partidos)->where('puedeValidar', true)->count())
@php($activas = $inscripciones->where('estado', '!=', \App\Enums\EstadoInscripcion::Baja)->count())
<div class="mx-auto max-w-4xl space-y-6 px-4 py-8" x-data="{ pestana: '{{ $porValidar > 0 ? 'partidos' : 'inscripciones' }}' }">
    <header class="flex items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-normal">Hola, {{ $yo->nombre }}</h1>
            <p class="text-sm text-tema-suave">Tus torneos, tus partidos y tus datos.</p>
        </div>
        <form method="POST" action="{{ route('jugador.salir') }}">
            @csrf
            <button type="submit" class="boton-claro px-4 py-1.5">Salir</button>
        </form>
    </header>

    @if ($aviso)
        <p class="rounded-[3px] bg-green-50 p-3 text-sm text-green-900">{{ $aviso }}</p>
    @endif
    @error('portal') <p class="rounded-[3px] bg-red-50 p-3 text-sm text-red-800">{{ $message }}</p> @enderror

    <nav class="pestanas" role="tablist">
        <button type="button" role="tab" class="pestana" x-on:click="pestana = 'inscripciones'" x-bind:aria-selected="pestana === 'inscripciones'">Inscripciones @if ($activas > 0)<span class="text-xs text-tema-suave">{{ $activas }}</span>@endif</button>
        <button type="button" role="tab" class="pestana" x-on:click="pestana = 'partidos'" x-bind:aria-selected="pestana === 'partidos'">Partidos @if ($porValidar > 0)<span class="contador">{{ $porValidar }}</span>@endif</button>
        @if ($torneosConHuecos->isNotEmpty())
            <button type="button" role="tab" class="pestana" x-on:click="pestana = 'disponibilidad'" x-bind:aria-selected="pestana === 'disponibilidad'">Cuándo no puedo</button>
        @endif
        <button type="button" role="tab" class="pestana" x-on:click="pestana = 'avisos'" x-bind:aria-selected="pestana === 'avisos'">Avisos</button>
        <button type="button" role="tab" class="pestana" x-on:click="pestana = 'datos'" x-bind:aria-selected="pestana === 'datos'">Mis datos</button>
    </nav>

    <section x-show="pestana === 'inscripciones'" @if ($porValidar > 0) x-cloak @endif>
        <h2 class="titulo-seccion">Mis inscripciones</h2>
        <ul class="mt-3 grid gap-4 sm:grid-cols-2">
            @forelse ($inscripciones as $inscripcion)
                @php($torneo = $inscripcion->categoria->torneo)
                @php($pendiente = $inscripcion->participante->participanteJugadores->first(fn ($f) => $f->confirmado_en === null))
                <li class="tarjeta p-4">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <a href="{{ route('jugador.inscripcion', $inscripcion) }}" class="text-sm font-semibold hover:underline">{{ $torneo->nombre }}</a>
                        <span @class(['rounded-[3px] px-2 py-0.5 text-xs font-medium', 'bg-green-50 text-green-800' => $inscripcion->estado === \App\Enums\EstadoInscripcion::Confirmada, 'bg-amber-50 text-amber-800' => $inscripcion->estado === \App\Enums\EstadoInscripcion::Pendiente, 'bg-tema-fondo text-tema-suave' => ! in_array($inscripcion->estado, [\App\Enums\EstadoInscripcion::Confirmada, \App\Enums\EstadoInscripcion::Pendiente], true)])>{{ $inscripcion->estado->etiqueta() }}@if ($inscripcion->posicion_espera) (#{{ $inscripcion->posicion_espera }})@endif</span>
                    </div>
                    <p class="mt-1 text-sm text-tema-suave">{{ $inscripcion->categoria->nombre }} · {{ $inscripcion->participante->nombre }}</p>
                    <p class="mt-1 text-sm text-tema-suave">Nivel:
                        @foreach ($inscripcion->participante->participanteJugadores as $fila)
                            {{ $fila->jugador->is($yo) ? 'tú' : $fila->jugador->nombre }} {{ $fila->jugador->nivel_declarado ?? '—' }}@if (! $loop->last) · @endif
                        @endforeach
                    </p>
                    <a href="{{ route('jugador.inscripcion', $inscripcion) }}" class="enlace mt-2 inline-block text-sm">Ver la inscripción y poner los niveles →</a>
                    @if ($pendiente && $inscripcion->estado === \App\Enums\EstadoInscripcion::Pendiente)
                        <p class="mt-1 text-sm text-amber-700">Falta que {{ $pendiente->jugador->nombre }} confirme desde su correo.</p>
                    @endif
                    @if ($torneo->estado === \App\Enums\EstadoTorneo::InscripcionAbierta && $inscripcion->estado !== \App\Enums\EstadoInscripcion::Baja)
                        <button type="button" wire:click="darmeDeBaja({{ $inscripcion->id }})" wire:confirm="¿Seguro? Tu pareja deja la categoría." class="mt-2 text-sm text-red-700 underline">Darme de baja</button>
                    @endif
                </li>
            @empty
                <li class="text-sm text-tema-suave">Aún no te has inscrito en ningún torneo.</li>
            @endforelse
        </ul>
    </section>

    <section x-show="pestana === 'partidos'" @if ($porValidar === 0) x-cloak @endif>
        <h2 class="titulo-seccion">Mis partidos</h2>
        <ul class="mt-3 grid gap-4 sm:grid-cols-2">
            @forelse ($partidos as $p)
                <li class="tarjeta p-4">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <span class="text-xs text-tema-suave">{{ $p['torneo'] }} · {{ $p['categoria'] }}</span>
                        <span class="text-lg font-light text-tema-azul">{{ $p['hora'] ?? 'Sin hora' }}</span>
                    </div>
                    <div class="mt-1 text-sm font-semibold">{{ $p['texto'] }}</div>
                    <div class="mt-1 text-sm">
                        @if ($p['resultado'] !== '') <span class="tabular-nums">{{ $p['resultado'] }}</span> · @endif
                        <span class="text-tema-suave">{{ $p['estado']->etiqueta() }}</span>
                    </div>
                    @if ($editando === $p['id'])
                        @include('livewire.publico._formulario-resultado', ['local' => $p['local'], 'visitante' => $p['visitante'], 'conEspeciales' => false])
                    @else
                        <div class="mt-2 flex gap-3">
                            @if ($p['puedeAnotar'])
                                <button type="button" wire:click="abrirResultado({{ $p['id'] }})" class="text-sm font-medium enlace">Anotar resultado</button>
                            @endif
                            @if ($p['puedeValidar'])
                                <button type="button" wire:click="validar({{ $p['id'] }})" class="text-sm font-medium enlace">Es correcto: validar</button>
                            @endif
                        </div>
                    @endif
                </li>
            @empty
                <li class="text-sm text-tema-suave">Tus partidos salen aquí cuando la organización hace el sorteo.</li>
            @endforelse
        </ul>
    </section>

    @if ($torneosConHuecos->isNotEmpty())
        <section x-show="pestana === 'disponibilidad'" x-cloak>
            <h2 class="titulo-seccion">Cuándo no puedo jugar</h2>
            <p class="mt-1 text-sm text-tema-suave">Antes de que salgan los horarios, dinos qué ratos no puedes. Se tienen en cuenta al planificar.</p>
            @foreach ($torneosConHuecos as $torneo)
                <div class="mt-4 tarjeta p-4">
                    <h3 class="font-medium">{{ $torneo->nombre }}</h3>
                    <ul class="mt-2 space-y-1 text-sm">
                        @foreach ($noDisponibilidades->get($torneo->id, collect()) as $hueco)
                            <li class="flex justify-between gap-3">
                                <span>{{ $hueco->desde->translatedFormat('l j') }}, de {{ $hueco->desde->format('H:i') }} a {{ $hueco->hasta->format('H:i') }}</span>
                                <button type="button" wire:click="quitarNoDisponible({{ $hueco->id }})" class="text-red-700 underline">Quitar</button>
                            </li>
                        @endforeach
                    </ul>
                    <form wire:submit="anotarNoDisponible({{ $torneo->id }})" class="mt-3 flex flex-wrap items-end gap-2 text-sm">
                        <label>Día
                            <select wire:model="noDisponible.{{ $torneo->id }}.dia" class="mt-1 block campo px-2 py-1">
                                <option value="">—</option>
                                @foreach ($torneo->fecha_inicio->toPeriod($torneo->fecha_fin) as $dia)
                                    <option value="{{ $dia->toDateString() }}">{{ $dia->translatedFormat('l j') }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Desde <input type="time" wire:model="noDisponible.{{ $torneo->id }}.desde" class="mt-1 block campo px-2 py-1"></label>
                        <label>Hasta <input type="time" wire:model="noDisponible.{{ $torneo->id }}.hasta" class="mt-1 block campo px-2 py-1"></label>
                        <button type="submit" class="boton px-4 py-1.5">Añadir</button>
                    </form>
                </div>
            @endforeach
        </section>
    @endif

    <section x-show="pestana === 'avisos'" x-cloak>
        <h2 class="titulo-seccion">Avisos</h2>
        <x-pwa.instalar class="mt-3" />
        <div class="mt-3 space-y-3 tarjeta p-4">
            <x-push.activar />
            <form wire:submit="guardarAvisos" class="flex flex-wrap items-center gap-4 text-sm">
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="avisosCorreo"> Por correo</label>
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="avisosPush"> En el móvil (push)</label>
                <button type="submit" class="boton px-4 py-1.5">Guardar</button>
            </form>
            <p class="text-xs text-tema-suave">Inscripciones, horarios, resultados por validar, cruces y los avisos de la organización.</p>
        </div>
        @if ($historial->isNotEmpty())
            <ul class="mt-3 divide-y divide-tema-borde tarjeta text-sm">
                @foreach ($historial as $notificacion)
                    <li class="flex justify-between gap-3 px-4 py-2">
                        <a href="{{ $notificacion->data['url'] ?? '/' }}" class="min-w-0">
                            <span class="block font-medium">{{ $notificacion->data['titulo'] ?? '' }}</span>
                            <span class="block truncate text-tema-suave">{{ $notificacion->data['texto'] ?? '' }}</span>
                        </a>
                        <span class="shrink-0 text-xs text-tema-suave">{{ $notificacion->created_at?->diffForHumans() }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section x-show="pestana === 'datos'" x-cloak class="space-y-8">
        <div>
        <h2 class="titulo-seccion">Mis datos</h2>
        <form wire:submit="guardarDatos" class="mt-3 grid gap-4 tarjeta p-4 sm:grid-cols-2">
            <label class="block text-sm"><span class="font-medium">Nombre</span><input type="text" wire:model="nombre" class="mt-1 w-full campo">@error('nombre') <span class="text-red-700">{{ $message }}</span> @enderror</label>
            <label class="block text-sm"><span class="font-medium">Apellidos</span><input type="text" wire:model="apellidos" class="mt-1 w-full campo">@error('apellidos') <span class="text-red-700">{{ $message }}</span> @enderror</label>
            <label class="block text-sm"><span class="font-medium">Teléfono</span><input type="tel" wire:model="telefono" class="mt-1 w-full campo"></label>
            <label class="block text-sm"><span class="font-medium">Nivel</span><input type="text" inputmode="decimal" wire:model="nivel" placeholder="3 o 2,5" class="mt-1 w-full campo">@error('nivel') <span class="text-red-700">{{ $message }}</span> @enderror<span class="text-tema-suave">Un número del 0 al 10. El de tu compañero lo pones en la inscripción: pulsa en el torneo.</span></label>
            <label class="block text-sm"><span class="font-medium">Alias</span><input type="text" wire:model="alias" class="mt-1 w-full campo"><span class="text-tema-suave">Lo que se enseña en los torneos con privacidad.</span></label>
            <p class="text-sm text-tema-suave sm:col-span-2">Tu correo: {{ $yo->getAttribute('email') }}. Para cambiarlo, escribe a la organización.</p>
            <div><button type="submit" class="boton">Guardar</button></div>
        </form>
        </div>

        <div>
        <h2 class="titulo-seccion">Borrar mis datos</h2>
        <p class="mt-1 text-sm text-tema-suave">Se borran tu nombre, tu correo y tu teléfono. Tus resultados se quedan, sin tu nombre. No se puede deshacer.</p>
        <form wire:submit="borrarMisDatos" class="mt-3 flex flex-wrap items-end gap-2 text-sm">
            <label>Escribe BORRAR <input type="text" wire:model="confirmarBorrado" class="mt-1 block campo px-2 py-1"></label>
            <button type="submit" class="rounded-full bg-red-700 px-4 py-1.5 font-medium text-white">Borrar mis datos</button>
        </form>
        @error('confirmarBorrado') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>
    </section>
</div>
