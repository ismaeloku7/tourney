<div class="space-y-6">
    <header>
        <h1 class="text-2xl font-semibold">Mis partidos</h1>
        <p class="mt-1 text-sm text-tema-suave">Los que te quedan por jugar y los ya jugados, de todos tus torneos.</p>
    </header>

    @include('livewire.jugador._aviso')

    <section>
        <h2 class="text-base font-semibold">Por jugar</h2>
        <ul class="mt-3 grid gap-3 sm:grid-cols-2">
            @forelse ($porJugar as $p)
                @include('livewire.jugador._partido', ['p' => $p])
            @empty
                <li class="text-sm text-tema-suave">Nada por jugar. Tus partidos salen aquí cuando la organización hace el sorteo.</li>
            @endforelse
        </ul>
    </section>

    @if ($jugados !== [])
        <section>
            <h2 class="text-base font-semibold">Jugados</h2>
            <ul class="mt-3 grid gap-3 sm:grid-cols-2">
                @foreach ($jugados as $p)
                    @include('livewire.jugador._partido', ['p' => $p])
                @endforeach
            </ul>
        </section>
    @endif
</div>
