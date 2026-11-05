<div class="space-y-6">
    <header>
        <h1 class="text-2xl font-semibold sm:text-3xl">Hola, {{ $yo->nombre }}</h1>
        <p class="mt-1 text-sm text-tema-suave">{{ $activas === 1 ? '1 torneo activo' : "{$activas} torneos activos" }}</p>
    </header>

    @include('livewire.jugador._aviso')

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @include('livewire.jugador._cifra', [
            'etiqueta' => 'Próximo partido',
            'valor' => $proximo === null ? 'Ninguno' : ($proximo['hora'] ?? 'Sin hora'),
            'detalle' => $proximo === null ? 'Saldrá cuando haya sorteo' : 'contra '.$proximo['rival'],
            'icono' => 'calendario',
            'url' => route('jugador.partidos'),
        ])
        @include('livewire.jugador._cifra', [
            'etiqueta' => 'Tu pareja',
            'valor' => $pareja === null ? '—' : ($pareja['companero']?->jugador->nombre_completo ?? 'Sin compañero'),
            'detalle' => $pareja === null ? 'Sin torneos activos' : $pareja['inscripcion']->categoria->torneo->nombre,
            'icono' => 'pareja',
            'url' => $pareja === null ? null : route('jugador.inscripcion', $pareja['inscripcion']),
        ])
        @include('livewire.jugador._cifra', [
            'etiqueta' => 'Por validar',
            'valor' => $porValidar,
            'detalle' => $porValidar === 0 ? 'nada pendiente' : ($porValidar === 1 ? 'resultado de tu rival' : 'resultados de tus rivales'),
            'icono' => 'validar',
            'url' => route('jugador.partidos'),
        ])
        @include('livewire.jugador._cifra', [
            'etiqueta' => 'Torneos',
            'valor' => $activas,
            'detalle' => $inscripciones->count() > $activas ? $inscripciones->count().' en total' : 'en marcha',
            'icono' => 'torneo',
            'url' => '#torneos',
        ])
    </div>

    <div class="grid gap-6 lg:grid-cols-[3fr_2fr]">
        <div class="space-y-6">
            <section>
                <h2 class="text-base font-semibold">
                    @if ($pendientes === []) Nada pendiente @elseif (count($pendientes) === 1) Tienes 1 cosa pendiente @else Tienes {{ count($pendientes) }} cosas pendientes @endif
                </h2>
                @if ($pendientes !== [])
                    <ul class="mt-3 space-y-2">
                        @foreach ($pendientes as $pendiente)
                            <li>
                                <a href="{{ $pendiente['url'] }}" class="tarjeta flex items-center gap-3 p-3 transition hover:ring-tema-azul">
                                    <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-amber-50 text-amber-700" aria-hidden="true">
                                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg>
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-sm font-medium">{{ $pendiente['texto'] }}</span>
                                        @if ($pendiente['detalle'])<span class="block text-xs text-tema-suave">{{ $pendiente['detalle'] }}</span>@endif
                                    </span>
                                    <span class="text-tema-suave" aria-hidden="true">›</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-2 text-sm text-tema-suave">Todo al día.</p>
                @endif
            </section>

            <section>
                <div class="flex items-baseline justify-between gap-3">
                    <h2 class="text-base font-semibold">Lo próximo</h2>
                    <a href="{{ route('jugador.partidos') }}" class="enlace text-sm">Ver partidos</a>
                </div>
                @if ($proximo === null)
                    <p class="mt-2 text-sm text-tema-suave">Tu próximo partido saldrá aquí cuando la organización haga el sorteo.</p>
                @else
                    <a href="{{ $proximoInscripcion === null ? route('jugador.partidos') : route('jugador.inscripcion', $proximoInscripcion) }}" class="mt-3 block overflow-hidden rounded-[3px] p-5 text-white transition hover:brightness-95" style="background: var(--acento)">
                        <div class="flex items-start gap-4">
                            <div class="shrink-0 rounded-[3px] bg-white/15 px-3 py-2 text-center leading-tight">
                                <div class="text-[0.65rem] font-semibold uppercase tracking-wide">{{ $proximo['hora'] !== null ? \Illuminate\Support\Str::before($proximo['hora'], ' ') : 'Partido' }}</div>
                                <div class="text-xl font-bold">{{ $proximo['hora'] !== null ? \Illuminate\Support\Str::of($proximo['hora'])->after(' ')->before(' ') : '?' }}</div>
                            </div>
                            <div class="min-w-0">
                                <div class="text-lg font-semibold leading-tight">Contra {{ $proximo['rival'] }}</div>
                                <div class="mt-1 text-sm text-white/85">{{ $proximo['torneo'] }} · {{ $proximo['categoria'] }}@if ($proximo['hora']) · {{ $proximo['hora'] }}@else · sin hora todavía @endif</div>
                                <div class="mt-2 inline-block rounded-full bg-white px-2.5 py-0.5 text-xs font-medium" style="color: var(--acento)">{{ $proximo['estado']->etiqueta() }}</div>
                            </div>
                        </div>
                    </a>
                @endif
            </section>
        </div>

        <div class="space-y-6">
            <section id="torneos">
                <h2 class="text-base font-semibold">Tus torneos</h2>
                <ul class="mt-3 space-y-3">
                    @forelse ($inscripciones as $inscripcion)
                        @php($torneo = $inscripcion->categoria->torneo)
                        @php($companero = $companeros[$inscripcion->id] ?? null)
                        <li>
                            <a href="{{ route('jugador.inscripcion', $inscripcion) }}" class="tarjeta block p-4 transition hover:ring-tema-azul">
                                <div class="flex items-start justify-between gap-3">
                                    <span class="min-w-0">
                                        <span class="block truncate font-semibold">{{ $torneo->nombre }}</span>
                                        <span class="block text-sm text-tema-suave">{{ $inscripcion->categoria->nombre }} · {{ $torneo->fecha_inicio->translatedFormat('j M') }}</span>
                                    </span>
                                    @include('livewire.jugador._estado-inscripcion', ['inscripcion' => $inscripcion])
                                </div>
                                <div class="mt-2 text-sm">
                                    <span class="font-medium">{{ $inscripcion->participante->nombre }}</span>
                                    @if ($companero === null)
                                        <span class="text-amber-700"> · sin compañero todavía</span>
                                    @elseif ($companero->confirmado_en === null && $inscripcion->estado === \App\Enums\EstadoInscripcion::Pendiente)
                                        <span class="text-amber-700"> · falta que {{ $companero->jugador->nombre }} confirme</span>
                                    @else
                                        <span class="text-tema-suave"> · con {{ $companero->jugador->nombre_completo }}</span>
                                    @endif
                                </div>
                            </a>
                        </li>
                    @empty
                        <li class="text-sm text-tema-suave">Aún no estás en ningún torneo.</li>
                    @endforelse
                </ul>
            </section>

            <section>
                <h2 class="text-base font-semibold">La app en el móvil</h2>
                <div class="mt-3">
                    <x-pwa.instalar />
                    <p class="text-sm text-tema-suave">Con la app instalada, los avisos de horarios, cruces y resultados te llegan al móvil. <a href="{{ route('jugador.avisos') }}" class="enlace">Elegir mis avisos</a></p>
                </div>
            </section>
        </div>
    </div>
</div>
