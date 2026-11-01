{{--
    El formulario de resultado del portal y de la mesa. Espera $local,
    $visitante y $conEspeciales (la mesa puede anotar walkover y demás).
--}}
<form wire:submit="guardarResultado" class="mt-3 space-y-3 rounded-lg bg-neutral-50 p-3">
    @if ($conEspeciales ?? false)
        <label class="block text-sm">
            <span class="font-medium">¿Se jugó?</span>
            <select wire:model.live="especial" class="mt-1 w-full rounded-lg border border-neutral-300 px-2 py-1.5">
                <option value="">Sí, anoto los sets</option>
                @foreach (\App\Enums\ResultadoEspecial::cases() as $caso)
                    <option value="{{ $caso->value }}">{{ $caso->etiqueta() }}</option>
                @endforeach
            </select>
        </label>
    @endif

    @if ($especial !== '')
        @if (\App\Enums\ResultadoEspecial::from($especial)->tieneGanador())
            <fieldset class="text-sm">
                <legend class="font-medium">Gana</legend>
                <label class="mr-4"><input type="radio" wire:model="ganaLocal" value="1"> {{ $local }}</label>
                <label><input type="radio" wire:model="ganaLocal" value="0"> {{ $visitante }}</label>
            </fieldset>
        @endif
    @else
        <table class="w-full text-sm">
            <thead class="text-left text-neutral-500">
                <tr><th class="font-normal">Set</th><th class="font-normal">{{ $local }}</th><th class="font-normal">{{ $visitante }}</th><th class="font-normal">Tie-break</th></tr>
            </thead>
            <tbody>
                @foreach ($sets as $i => $set)
                    <tr>
                        <td class="py-1">{{ $i + 1 }}</td>
                        <td><input type="number" min="0" max="99" inputmode="numeric" wire:model="sets.{{ $i }}.juegos_local" class="w-14 rounded border border-neutral-300 px-2 py-1"></td>
                        <td><input type="number" min="0" max="99" inputmode="numeric" wire:model="sets.{{ $i }}.juegos_visitante" class="w-14 rounded border border-neutral-300 px-2 py-1"></td>
                        <td class="whitespace-nowrap">
                            <input type="number" min="0" max="99" inputmode="numeric" wire:model="sets.{{ $i }}.tie_break_local" class="w-12 rounded border border-neutral-300 px-1 py-1" aria-label="Tie-break {{ $local }}">
                            <input type="number" min="0" max="99" inputmode="numeric" wire:model="sets.{{ $i }}.tie_break_visitante" class="w-12 rounded border border-neutral-300 px-1 py-1" aria-label="Tie-break {{ $visitante }}">
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="text-xs text-neutral-500">El tercer set en blanco si no se jugó. Un súper tie-break se anota con sus puntos (10-8) en el tercero.</p>
    @endif

    @error('resultado') <p class="text-sm text-red-700">{{ $message }}</p> @enderror

    <div class="flex gap-2">
        <button type="submit" class="rounded-lg px-4 py-2 text-sm font-medium text-white" style="background: var(--acento)">Guardar</button>
        <button type="button" wire:click="cerrarResultado" class="rounded-lg px-4 py-2 text-sm text-neutral-600 hover:bg-neutral-100">Cancelar</button>
    </div>
</form>
