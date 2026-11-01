<div class="mx-auto max-w-3xl space-y-10 px-4 py-10">
    <header class="flex items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold">Hola, {{ $yo->nombre }}</h1>
            <p class="text-sm text-neutral-500">Tus torneos, tus partidos y tus datos.</p>
        </div>
        <form method="POST" action="{{ route('jugador.salir') }}">
            @csrf
            <button type="submit" class="rounded-lg px-3 py-2 text-sm text-neutral-600 hover:bg-neutral-100">Salir</button>
        </form>
    </header>

    @if ($aviso)
        <p class="rounded-lg bg-green-50 p-3 text-sm text-green-900">{{ $aviso }}</p>
    @endif
    @error('portal') <p class="rounded-lg bg-red-50 p-3 text-sm text-red-800">{{ $message }}</p> @enderror

    <section>
        <h2 class="text-lg font-semibold">Mis inscripciones</h2>
        <ul class="mt-3 space-y-3">
            @forelse ($inscripciones as $inscripcion)
                @php($torneo = $inscripcion->categoria->torneo)
                @php($pendiente = $inscripcion->participante->participanteJugadores->first(fn ($f) => $f->confirmado_en === null))
                <li class="rounded-xl border border-neutral-200 bg-white p-4">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <a href="{{ route('torneo.ficha', $torneo) }}" class="font-semibold underline-offset-2 hover:underline">{{ $torneo->nombre }}</a>
                        <span class="rounded-full bg-neutral-100 px-2 py-0.5 text-xs font-medium">{{ $inscripcion->estado->etiqueta() }}@if ($inscripcion->posicion_espera) (#{{ $inscripcion->posicion_espera }})@endif</span>
                    </div>
                    <p class="mt-1 text-sm text-neutral-600">{{ $inscripcion->categoria->nombre }} · {{ $inscripcion->participante->nombre }}</p>
                    @if ($pendiente && $inscripcion->estado === \App\Enums\EstadoInscripcion::Pendiente)
                        <p class="mt-1 text-sm text-amber-700">Falta que {{ $pendiente->jugador->nombre }} confirme desde su correo.</p>
                    @endif
                    @if ($torneo->estado === \App\Enums\EstadoTorneo::InscripcionAbierta && $inscripcion->estado !== \App\Enums\EstadoInscripcion::Baja)
                        <button type="button" wire:click="darmeDeBaja({{ $inscripcion->id }})" wire:confirm="¿Seguro? Tu pareja deja la categoría." class="mt-2 text-sm text-red-700 underline">Darme de baja</button>
                    @endif
                </li>
            @empty
                <li class="text-neutral-600">Aún no te has inscrito en ningún torneo.</li>
            @endforelse
        </ul>
    </section>

    <section>
        <h2 class="text-lg font-semibold">Mis partidos</h2>
        <ul class="mt-3 space-y-3">
            @forelse ($partidos as $p)
                <li class="rounded-xl border border-neutral-200 bg-white p-4">
                    <div class="flex flex-wrap justify-between gap-2 text-sm text-neutral-500">
                        <span>{{ $p['torneo'] }} · {{ $p['categoria'] }}</span>
                        <span>{{ $p['hora'] ?? 'Sin hora todavía' }}</span>
                    </div>
                    <div class="mt-1 font-semibold">{{ $p['texto'] }}</div>
                    <div class="mt-1 text-sm">
                        @if ($p['resultado'] !== '') <span class="tabular-nums">{{ $p['resultado'] }}</span> · @endif
                        <span class="text-neutral-500">{{ $p['estado']->etiqueta() }}</span>
                    </div>
                    @if ($editando === $p['id'])
                        @include('livewire.publico._formulario-resultado', ['local' => $p['local'], 'visitante' => $p['visitante'], 'conEspeciales' => false])
                    @else
                        <div class="mt-2 flex gap-3">
                            @if ($p['puedeAnotar'])
                                <button type="button" wire:click="abrirResultado({{ $p['id'] }})" class="text-sm font-medium underline" style="color: var(--acento)">Anotar resultado</button>
                            @endif
                            @if ($p['puedeValidar'])
                                <button type="button" wire:click="validar({{ $p['id'] }})" class="text-sm font-medium underline" style="color: var(--acento)">Es correcto: validar</button>
                            @endif
                        </div>
                    @endif
                </li>
            @empty
                <li class="text-neutral-600">Tus partidos salen aquí cuando la organización hace el sorteo.</li>
            @endforelse
        </ul>
    </section>

    @if ($torneosConHuecos->isNotEmpty())
        <section>
            <h2 class="text-lg font-semibold">Cuándo no puedo jugar</h2>
            <p class="mt-1 text-sm text-neutral-600">Antes de que salgan los horarios, dinos qué ratos no puedes. Se tienen en cuenta al planificar.</p>
            @foreach ($torneosConHuecos as $torneo)
                <div class="mt-4 rounded-xl border border-neutral-200 bg-white p-4">
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
                            <select wire:model="noDisponible.{{ $torneo->id }}.dia" class="mt-1 block rounded border border-neutral-300 px-2 py-1">
                                <option value="">—</option>
                                @foreach ($torneo->fecha_inicio->toPeriod($torneo->fecha_fin) as $dia)
                                    <option value="{{ $dia->toDateString() }}">{{ $dia->translatedFormat('l j') }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Desde <input type="time" wire:model="noDisponible.{{ $torneo->id }}.desde" class="mt-1 block rounded border border-neutral-300 px-2 py-1"></label>
                        <label>Hasta <input type="time" wire:model="noDisponible.{{ $torneo->id }}.hasta" class="mt-1 block rounded border border-neutral-300 px-2 py-1"></label>
                        <button type="submit" class="rounded-lg px-3 py-1.5 font-medium text-white" style="background: var(--acento)">Añadir</button>
                    </form>
                </div>
            @endforeach
        </section>
    @endif

    <section>
        <h2 class="text-lg font-semibold">Mis datos</h2>
        <form wire:submit="guardarDatos" class="mt-3 grid gap-4 rounded-xl border border-neutral-200 bg-white p-4 sm:grid-cols-2">
            <label class="block text-sm"><span class="font-medium">Nombre</span><input type="text" wire:model="nombre" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2">@error('nombre') <span class="text-red-700">{{ $message }}</span> @enderror</label>
            <label class="block text-sm"><span class="font-medium">Apellidos</span><input type="text" wire:model="apellidos" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2">@error('apellidos') <span class="text-red-700">{{ $message }}</span> @enderror</label>
            <label class="block text-sm"><span class="font-medium">Teléfono</span><input type="tel" wire:model="telefono" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2"></label>
            <label class="block text-sm"><span class="font-medium">Alias</span><input type="text" wire:model="alias" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2"><span class="text-neutral-500">Lo que se enseña en los torneos con privacidad.</span></label>
            <p class="text-sm text-neutral-500 sm:col-span-2">Tu correo: {{ $yo->getAttribute('email') }}. Para cambiarlo, escribe a la organización.</p>
            <div><button type="submit" class="rounded-lg px-4 py-2 text-sm font-medium text-white" style="background: var(--acento)">Guardar</button></div>
        </form>
    </section>

    <section>
        <h2 class="text-lg font-semibold">Borrar mis datos</h2>
        <p class="mt-1 text-sm text-neutral-600">Se borran tu nombre, tu correo y tu teléfono. Tus resultados se quedan, sin tu nombre. No se puede deshacer.</p>
        <form wire:submit="borrarMisDatos" class="mt-3 flex flex-wrap items-end gap-2 text-sm">
            <label>Escribe BORRAR <input type="text" wire:model="confirmarBorrado" class="mt-1 block rounded border border-neutral-300 px-2 py-1"></label>
            <button type="submit" class="rounded-lg bg-red-700 px-3 py-1.5 font-medium text-white">Borrar mis datos</button>
        </form>
        @error('confirmarBorrado') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
    </section>
</div>
