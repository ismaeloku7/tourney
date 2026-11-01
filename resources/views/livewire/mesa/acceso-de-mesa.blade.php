<div class="mx-auto max-w-md px-4 py-12">
    <h1 class="text-2xl font-semibold">Mesa</h1>
    <p class="mt-2 text-neutral-600">Para árbitros y equipo: los partidos de tus pistas y sus resultados.</p>
    <form wire:submit="entrar" class="mt-6 space-y-4">
        <label class="block"><span class="text-sm font-medium">Correo electrónico</span><input type="email" wire:model="email" autocomplete="email" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2"></label>
        <label class="block"><span class="text-sm font-medium">Contraseña</span><input type="password" wire:model="clave" autocomplete="current-password" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2"></label>
        @error('email') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
        @error('clave') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
        <button type="submit" class="rounded-lg px-5 py-2.5 font-medium text-white" style="background: var(--acento)">Entrar</button>
    </form>
</div>
