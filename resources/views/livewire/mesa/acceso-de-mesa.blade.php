<div class="mx-auto max-w-md px-4 py-12">
    <h1 class="text-2xl font-normal">Mesa</h1>
    <p class="mt-2 text-neutral-600">Para árbitros y equipo: los partidos de tus pistas y sus resultados.</p>
    <form wire:submit="entrar" class="mt-6 space-y-4">
        <label class="block"><span class="text-sm font-medium">Correo electrónico</span><input type="email" wire:model="email" autocomplete="email" class="mt-1 w-full campo"></label>
        <label class="block"><span class="text-sm font-medium">Contraseña</span><input type="password" wire:model="clave" autocomplete="current-password" class="mt-1 w-full campo"></label>
        @error('email') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
        @error('clave') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
        <button type="submit" class="boton px-6 py-2.5">Entrar</button>
    </form>
</div>
