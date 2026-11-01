<div class="mx-auto max-w-2xl px-4 py-10">
    <p class="text-sm text-neutral-500"><a href="{{ route('torneo.ficha', $torneo) }}" class="underline">{{ $torneo->nombre }}</a></p>
    <h1 class="mt-1 text-2xl font-semibold">Inscripción</h1>

    @if (! $abierta)
        <p class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-5 text-amber-900">{{ $motivo }}</p>
    @elseif ($inscripcion)
        <div class="mt-6 rounded-xl border border-green-200 bg-green-50 p-6 text-green-900">
            <h2 class="text-lg font-semibold">Inscripción enviada</h2>
            <p class="mt-2">Te has apuntado en <strong>{{ $inscripcion->categoria->nombre }}</strong> como <strong>{{ $inscripcion->participante->nombre }}</strong>.</p>
            <p class="mt-2">Hemos mandado a tu compañero un correo para que confirme. Hasta que lo haga (tiene 48 horas), la inscripción no ocupa plaza.</p>
            <a href="{{ route('jugador.portal') }}" class="mt-4 inline-block underline">Ver mis inscripciones</a>
        </div>
    @elseif (! $yo)
        @if ($enlaceEnviado)
            <div class="mt-6 rounded-xl border border-green-200 bg-green-50 p-5 text-green-900">
                Ya hay una cuenta de jugador con <strong>{{ $email }}</strong>. Te hemos mandado un enlace para entrar: al abrirlo vuelves aquí para terminar la inscripción.
            </div>
        @else
            <p class="mt-2 text-neutral-600">Primero, tus datos. Si ya has jugado con nosotros, basta con tu correo: te mandamos un enlace para entrar.</p>
            <form wire:submit="identificarme" class="mt-6 space-y-4" novalidate>
                <label class="block">
                    <span class="text-sm font-medium">Correo electrónico</span>
                    <input type="email" wire:model="email" autocomplete="email" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2">
                    @error('email') <span class="mt-1 block text-sm text-red-700">{{ $message }}</span> @enderror
                </label>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block">
                        <span class="text-sm font-medium">Nombre</span>
                        <input type="text" wire:model="nombre" autocomplete="given-name" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2">
                        @error('nombre') <span class="mt-1 block text-sm text-red-700">{{ $message }}</span> @enderror
                    </label>
                    <label class="block">
                        <span class="text-sm font-medium">Apellidos</span>
                        <input type="text" wire:model="apellidos" autocomplete="family-name" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2">
                        @error('apellidos') <span class="mt-1 block text-sm text-red-700">{{ $message }}</span> @enderror
                    </label>
                </div>
                <label class="block">
                    <span class="text-sm font-medium">Teléfono <span class="font-normal text-neutral-500">(opcional; solo lo ve la organización)</span></span>
                    <input type="tel" wire:model="telefono" autocomplete="tel" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2">
                </label>
                <div class="hidden" aria-hidden="true"><label>Sitio web <input type="text" wire:model="sitio_web" tabindex="-1" autocomplete="off"></label></div>
                <label class="flex items-start gap-3 text-sm">
                    <input type="checkbox" wire:model="privacidad" class="mt-1">
                    <span>He leído la <a href="{{ route('legal.privacidad') }}" target="_blank" class="underline">política de privacidad</a>.</span>
                </label>
                @error('privacidad') <span class="block text-sm text-red-700">{{ $message }}</span> @enderror
                @if ($condiciones)
                    <details class="rounded-lg border border-neutral-200 bg-white p-3 text-sm"><summary class="cursor-pointer font-medium">Condiciones de inscripción</summary><div class="mt-2 whitespace-pre-line text-neutral-700">{{ $condiciones }}</div></details>
                    <label class="flex items-start gap-3 text-sm"><input type="checkbox" wire:model="condiciones" class="mt-1"><span>Acepto las condiciones de inscripción.</span></label>
                    @error('condiciones') <span class="block text-sm text-red-700">{{ $message }}</span> @enderror
                @endif
                @error('inscripcion') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
                <button type="submit" class="rounded-lg px-5 py-2.5 font-medium text-white" style="background: var(--acento)">Seguir</button>
            </form>
        @endif
    @else
        <p class="mt-2 text-neutral-600">Hola, {{ $yo->nombre }}. Elige la categoría y dinos con quién juegas.</p>
        <form wire:submit="inscribirme" class="mt-6 space-y-4" novalidate>
            <fieldset class="space-y-2">
                <legend class="text-sm font-medium">Categoría</legend>
                @foreach ($categorias as $categoria)
                    @php($libres = $categoria->cupo === null ? null : max(0, $categoria->cupo - $categoria->confirmadas_count))
                    <label class="flex items-center justify-between gap-3 rounded-lg border border-neutral-200 bg-white px-3 py-2">
                        <span><input type="radio" wire:model="categoria_id" value="{{ $categoria->id }}" class="mr-2">{{ $categoria->nombre }}@if ($categoria->precio) · {{ number_format((float) $categoria->precio, 2, ',', '.') }} € @endif</span>
                        <span class="text-sm text-neutral-500">{{ $libres === null ? '' : ($libres === 0 ? 'Completa: lista de espera' : $libres.' plazas') }}</span>
                    </label>
                @endforeach
                @error('categoria_id') <span class="block text-sm text-red-700">{{ $message }}</span> @enderror
            </fieldset>
            <fieldset class="space-y-3 rounded-xl border border-neutral-200 bg-white p-4">
                <legend class="px-1 text-sm font-medium">Tu compañero</legend>
                <label class="block">
                    <span class="text-sm">Su correo electrónico</span>
                    <input type="email" wire:model="companero_email" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2">
                    @error('companero_email') <span class="mt-1 block text-sm text-red-700">{{ $message }}</span> @enderror
                </label>
                <p class="text-xs text-neutral-500">Si ya ha jugado con nosotros, basta el correo. Si no, escribe también su nombre y apellidos. Le llegará un correo para confirmar.</p>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block"><span class="text-sm">Nombre</span><input type="text" wire:model="companero_nombre" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2"></label>
                    <label class="block"><span class="text-sm">Apellidos</span><input type="text" wire:model="companero_apellidos" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2"></label>
                </div>
                <label class="block"><span class="text-sm">Teléfono <span class="text-neutral-500">(opcional)</span></span><input type="tel" wire:model="companero_telefono" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2"></label>
            </fieldset>
            <label class="block"><span class="text-sm font-medium">Notas para la organización <span class="font-normal text-neutral-500">(opcional)</span></span><textarea wire:model="notas" rows="2" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2"></textarea></label>
            @error('inscripcion') <p class="rounded-lg bg-red-50 p-3 text-sm text-red-800">{{ $message }}</p> @enderror
            <button type="submit" class="rounded-lg px-5 py-2.5 font-medium text-white" style="background: var(--acento)">Inscribirnos</button>
        </form>
    @endif
</div>
