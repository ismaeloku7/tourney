<div class="mx-auto max-w-xl px-4 py-12">
    @if ($confirmada)
        <div class="rounded-xl border border-green-200 bg-green-50 p-6 text-green-900">
            <h1 class="text-xl font-semibold">¡Hecho!</h1>
            <p class="mt-2">Tu inscripción está <strong>{{ mb_strtolower($confirmada) }}</strong>.</p>
            <a href="{{ route('jugador.portal') }}" class="mt-4 inline-block rounded-lg px-5 py-2.5 font-medium text-white" style="background: var(--acento)">Ver mis torneos</a>
        </div>
    @elseif ($invitacion === null)
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-6 text-amber-900">
            <h1 class="text-xl font-semibold">Esta invitación ya no vale</h1>
            <p class="mt-2">Ha caducado o ya se confirmó. Si querías jugar, pide a tu compañero que te vuelva a inscribir.</p>
        </div>
    @else
        @php($categoria = $invitacion->participante->categoria)
        <h1 class="text-2xl font-semibold">Hola, {{ $invitacion->jugador->nombre }}</h1>
        <p class="mt-3 text-neutral-700">
            <strong>{{ $quienInvita?->nombre_completo ?? 'Alguien' }}</strong> te ha inscrito como su pareja en <strong>{{ $categoria->nombre }}</strong> del torneo
            <a href="{{ route('torneo.ficha', $categoria->torneo) }}" class="underline">{{ $categoria->torneo->nombre }}</a>
            ({{ $categoria->torneo->fecha_inicio->translatedFormat('j \d\e F') }}).
        </p>
        <form wire:submit="confirmar" class="mt-6 space-y-4">
            @if ($invitacion->jugador->consentimiento_en === null)
                <label class="flex items-start gap-3 text-sm">
                    <input type="checkbox" wire:model="privacidad" class="mt-1">
                    <span>He leído la <a href="{{ route('legal.privacidad') }}" target="_blank" class="underline">política de privacidad</a>.</span>
                </label>
                @error('privacidad') <span class="block text-sm text-red-700">{{ $message }}</span> @enderror
            @endif
            <button type="submit" class="rounded-lg px-5 py-2.5 font-medium text-white" style="background: var(--acento)">Confirmar que juego</button>
        </form>
    @endif
</div>
