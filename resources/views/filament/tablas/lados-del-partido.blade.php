{{-- Los dos lados de un partido: las parejas enlazan a su ficha; un hueco por llenar, con su origen. --}}
@php
    /** @var \App\Models\Partido $partido */
    $partido = $getRecord();
    $lados = [[$partido->local, \App\Filament\Competicion\Marcador::lado($partido, true)], [$partido->visitante, \App\Filament\Competicion\Marcador::lado($partido, false)]];
@endphp
<div class="fi-ta-text grid gap-y-1 px-3 py-4 text-sm font-semibold">
    @foreach ($lados as [$participante, $texto])
        @if ($participante?->inscripcion !== null)
            <x-filament::link :href="\App\Filament\Resources\Parejas\ParejaResource::getUrl('edit', ['record' => $participante->inscripcion])" size="sm" color="gray" weight="semibold">{{ $texto }}</x-filament::link>
        @else
            <span class="{{ $participante === null ? 'font-normal text-gray-500 dark:text-gray-400' : '' }}">{{ $texto }}</span>
        @endif
    @endforeach
</div>
