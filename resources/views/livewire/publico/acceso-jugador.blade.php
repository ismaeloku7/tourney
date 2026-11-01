<div class="mx-auto max-w-md px-4 py-12">
    <h1 class="text-2xl font-semibold">Entrar como jugador</h1>
    @if ($enviado)
        <div class="mt-6 rounded-xl border border-green-200 bg-green-50 p-5 text-green-900">
            Si <strong>{{ $email }}</strong> es el correo de un jugador, te acabamos de mandar un enlace para entrar. Vale 15 minutos.
        </div>
    @else
        <p class="mt-2 text-neutral-600">Te mandamos un enlace al correo para entrar sin contraseña. ¿Aún no tienes cuenta? Se crea al inscribirte en un torneo.</p>
        <form wire:submit="enviar" class="mt-6 space-y-4">
            <label class="block">
                <span class="text-sm font-medium">Correo electrónico</span>
                <input type="email" wire:model="email" autocomplete="email" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2">
                @error('email') <span class="mt-1 block text-sm text-red-700">{{ $message }}</span> @enderror
            </label>
            <button type="submit" class="rounded-lg px-5 py-2.5 font-medium text-white" style="background: var(--acento)">Mandarme el enlace</button>
        </form>
    @endif
</div>
