{{-- La portada del organizador: sus torneos abiertos, en juego, próximos y recientes. --}}
<x-layouts.publico>
    <div class="mx-auto max-w-5xl px-4 py-10">
        <h1 class="text-3xl font-semibold">{{ \App\Filament\MarcaDelOrganizador::nombre() }}</h1>

        @php($bloques = [
            ['Inscripción abierta', $abiertos],
            ['En juego', $enJuego],
            ['Próximamente', $proximos],
            ['Recientes', $recientes],
        ])

        @if (collect($bloques)->every(fn ($b) => $b[1]->isEmpty()))
            <p class="mt-6 text-lg text-neutral-600">{{ __('Todavía no hay torneos publicados.') }}</p>
        @endif

        @foreach ($bloques as [$titulo, $torneos])
            @if ($torneos->isNotEmpty())
                <section class="mt-10">
                    <h2 class="text-lg font-semibold">{{ $titulo }}</h2>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        @foreach ($torneos as $torneo)
                            <a href="{{ route('torneo.ficha', $torneo) }}" class="block rounded-xl border border-neutral-200 bg-white p-5 hover:border-neutral-400">
                                <span class="block font-semibold">{{ $torneo->nombre }}</span>
                                <span class="mt-1 block text-sm text-neutral-600">
                                    {{ $torneo->fecha_inicio->translatedFormat('j \d\e F') }}@if (! $torneo->fecha_fin->isSameDay($torneo->fecha_inicio)) – {{ $torneo->fecha_fin->translatedFormat('j \d\e F') }}@endif
                                    @if ($torneo->sede) · {{ $torneo->sede->nombre }} @endif
                                </span>
                                <span class="mt-2 inline-block rounded-full bg-neutral-100 px-2 py-0.5 text-xs font-medium text-neutral-700">{{ $torneo->estado->etiqueta() }}</span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif
        @endforeach
    </div>
</x-layouts.publico>
