<div class="mx-auto max-w-3xl space-y-6 px-4 py-8">
    <a href="{{ route('jugador.portal') }}" class="enlace text-sm">← Mis torneos</a>

    <header class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-normal">{{ $torneo->nombre }}</h1>
            <p class="text-sm text-tema-suave">
                {{ $inscripcion->categoria->nombre }} ·
                {{ $torneo->fecha_inicio->isSameDay($torneo->fecha_fin) ? $torneo->fecha_inicio->translatedFormat('j \d\e F') : $torneo->fecha_inicio->translatedFormat('j').'–'.$torneo->fecha_fin->translatedFormat('j \d\e F') }}
            </p>
        </div>
        <span @class([
            'rounded-[3px] px-2 py-0.5 text-xs font-medium',
            'bg-green-50 text-green-800' => $inscripcion->estado === \App\Enums\EstadoInscripcion::Confirmada,
            'bg-amber-50 text-amber-800' => $inscripcion->estado === \App\Enums\EstadoInscripcion::Pendiente,
            'bg-tema-fondo text-tema-suave' => ! in_array($inscripcion->estado, [\App\Enums\EstadoInscripcion::Confirmada, \App\Enums\EstadoInscripcion::Pendiente], true),
        ])>{{ $inscripcion->estado->etiqueta() }}@if ($inscripcion->posicion_espera) (#{{ $inscripcion->posicion_espera }})@endif</span>
    </header>

    @if ($aviso)
        <p class="rounded-[3px] bg-green-50 p-3 text-sm text-green-900">{{ $aviso }}</p>
    @endif

    <section class="tarjeta p-5">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="text-sm font-semibold">Pareja: {{ $inscripcion->participante->nombre }}</h2>
            @if ($media)
                <span class="text-sm text-tema-suave">{{ $media }}</span>
            @endif
        </div>

        <form wire:submit="guardarNiveles" class="mt-4 space-y-3">
            @foreach ($jugadores as $jugador)
                <div class="flex flex-wrap items-end justify-between gap-3 border-t border-tema-borde pt-3 first:border-t-0 first:pt-0">
                    <div class="min-w-0">
                        <div class="text-sm font-medium">{{ $jugador->nombre_completo }} @if ($jugador->is($yo))<span class="text-tema-suave">(tú)</span>@endif</div>
                        @if ($loop->first)
                            <div class="text-xs text-tema-suave">Capitán</div>
                        @endif
                    </div>
                    <label class="text-sm">Nivel
                        <input type="text" inputmode="decimal" wire:model="niveles.{{ $jugador->id }}" placeholder="3 o 2,5" @disabled(! $editable) class="campo mt-1 block w-28 px-2 py-1 disabled:bg-tema-fondo">
                    </label>
                </div>
                @error('niveles.'.$jugador->id) <p class="text-sm text-red-700">{{ $message }}</p> @enderror
            @endforeach

            @if ($editable)
                <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
                    <p class="text-xs text-tema-suave">Un número del 0 al 10. Cualquiera de los dos puede poner el nivel de ambos.</p>
                    <button type="submit" class="boton">Guardar niveles</button>
                </div>
            @else
                <p class="pt-2 text-xs text-tema-suave">El torneo ha terminado o la pareja está de baja: los niveles ya no se cambian desde aquí.</p>
            @endif
        </form>
    </section>

    <a href="{{ route('torneo.ficha', $torneo) }}" class="enlace text-sm">Ver la web del torneo: horarios, grupos y resultados →</a>
</div>
