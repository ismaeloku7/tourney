{{-- Los jugadores de una pareja (inscripción), cada uno con enlace a su ficha; en la vista para copiar, sin enlace. --}}
@php
    /** @var \App\Models\Inscripcion $inscripcion */
    $inscripcion = $getRecord();
    $paraCopiar = \App\Filament\Resources\Parejas\ParaCopiar::activa($getLivewire());
@endphp
<div class="fi-ta-text grid gap-y-1 px-3 py-4">
    @forelse ($inscripcion->participante->jugadores as $jugador)
        @if ($paraCopiar)
            <span class="fi-ta-text-item text-sm text-gray-950 dark:text-white">{{ $jugador->nombre_completo }}</span>
        @else
            <x-filament::link :href="\App\Filament\Resources\Jugadores\JugadorResource::getUrl('edit', ['record' => $jugador])" size="sm" color="gray" class="fi-ta-text-item">
                {{ $jugador->nombre_completo }}
            </x-filament::link>
        @endif
    @empty
        <span class="fi-ta-placeholder text-sm text-gray-400 dark:text-gray-500">—</span>
    @endforelse
</div>
