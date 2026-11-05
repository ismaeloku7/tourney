{{-- Los jugadores de una pareja (inscripción), cada uno con enlace a su ficha. --}}
@php
    /** @var \App\Models\Inscripcion $inscripcion */
    $inscripcion = $getRecord();
@endphp
<div class="fi-ta-text grid gap-y-1 px-3 py-4">
    @forelse ($inscripcion->participante->jugadores as $jugador)
        <x-filament::link :href="\App\Filament\Resources\Jugadores\JugadorResource::getUrl('edit', ['record' => $jugador])" size="sm" color="gray" class="fi-ta-text-item">
            {{ $jugador->nombre_completo }}
        </x-filament::link>
    @empty
        <span class="fi-ta-placeholder text-sm text-gray-400 dark:text-gray-500">—</span>
    @endforelse
</div>
