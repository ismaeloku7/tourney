<div class="space-y-8">
    <header>
        <h1 class="text-2xl font-semibold">Mis datos</h1>
        <p class="mt-1 text-sm text-tema-suave">Lo que la organización y tus rivales ven de ti.</p>
    </header>

    @include('livewire.jugador._aviso')

    <form wire:submit="guardarDatos" class="grid gap-4 tarjeta p-5 sm:grid-cols-2">
        <label class="block text-sm"><span class="font-medium">Nombre</span><input type="text" wire:model="nombre" class="mt-1 w-full campo">@error('nombre') <span class="text-red-700">{{ $message }}</span> @enderror</label>
        <label class="block text-sm"><span class="font-medium">Apellidos</span><input type="text" wire:model="apellidos" class="mt-1 w-full campo">@error('apellidos') <span class="text-red-700">{{ $message }}</span> @enderror</label>
        <label class="block text-sm"><span class="font-medium">Teléfono</span><input type="tel" wire:model="telefono" class="mt-1 w-full campo"><span class="text-tema-suave">Con prefijo si no es de España. Solo lo ve la organización.</span></label>
        <label class="block text-sm"><span class="font-medium">Nivel</span><input type="text" inputmode="decimal" wire:model="nivel" placeholder="3 o 2,5" class="mt-1 w-full campo">@error('nivel') <span class="text-red-700">{{ $message }}</span> @enderror<span class="text-tema-suave">Un número del 0 al 10, como 3 o 2,5.</span></label>
        <label class="block text-sm"><span class="font-medium">Alias</span><input type="text" wire:model="alias" class="mt-1 w-full campo"><span class="text-tema-suave">Lo que se enseña en los torneos con privacidad.</span></label>
        <p class="self-end text-sm text-tema-suave">Tu correo: {{ $yo->getAttribute('email') }}. Para cambiarlo, escribe a la organización.</p>
        @if ($listaDeCompaneros->isNotEmpty())
            <div class="sm:col-span-2 border-t border-tema-borde pt-4">
                <h2 class="text-sm font-semibold">El nivel de tus compañeros</h2>
                <p class="text-xs text-tema-suave">Cualquiera de los dos puede poner el de ambos, mientras el torneo no haya terminado.</p>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    @foreach ($listaDeCompaneros as $companero)
                        <label class="block text-sm" wire:key="companero-{{ $companero->id }}"><span class="font-medium">{{ $companero->nombre_completo }}</span><input type="text" inputmode="decimal" wire:model="companeros.{{ $companero->id }}" placeholder="3 o 2,5" class="mt-1 w-full campo">@error('companeros.'.$companero->id) <span class="text-red-700">{{ $message }}</span> @enderror</label>
                    @endforeach
                </div>
            </div>
        @endif
        <div class="sm:col-span-2"><button type="submit" class="boton">Guardar</button></div>
    </form>

    <section class="tarjeta p-5">
        <h2 class="text-base font-semibold">Borrar mis datos</h2>
        <p class="mt-1 text-sm text-tema-suave">Se borran tu nombre, tu correo y tu teléfono. Tus resultados se quedan, sin tu nombre. No se puede deshacer.</p>
        <form wire:submit="borrarMisDatos" class="mt-3 flex flex-wrap items-end gap-2 text-sm">
            <label>Escribe BORRAR <input type="text" wire:model="confirmarBorrado" class="mt-1 block campo px-2 py-1"></label>
            <button type="submit" class="rounded-full bg-red-700 px-4 py-2 font-medium text-white">Borrar mis datos</button>
        </form>
        @error('confirmarBorrado') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
    </section>
</div>
