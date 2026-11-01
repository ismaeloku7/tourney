<div class="mx-auto max-w-2xl px-4 py-10">
    @if ($enviada)
        <div class="rounded-xl border border-green-200 bg-green-50 p-6">
            <h1 class="text-xl font-semibold text-green-900">Revisa tu correo</h1>
            <p class="mt-2 text-green-900">Hemos escrito a <strong>{{ $email }}</strong> con un enlace para activar <strong>{{ $nombre }}</strong>. Vale 48 horas. Cuando lo abras, tu panel estará en <strong>https://{{ $slug }}.{{ $dominio }}/panel</strong>.</p>
        </div>
    @else
        <h1 class="text-2xl font-semibold">Crear mi organización</h1>
        <p class="mt-2 text-neutral-600">Gratis para empezar: un torneo activo, 16 parejas por torneo y 2 categorías. Sin tarjeta.</p>

        <form wire:submit="enviar" class="mt-8 space-y-6" novalidate>
            <fieldset class="space-y-4 rounded-xl border border-neutral-200 bg-white p-6">
                <legend class="px-1 font-medium">La organización</legend>

                <label class="block">
                    <span class="text-sm font-medium">Nombre</span>
                    <input type="text" wire:model.live.blur="nombre" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2" autocomplete="organization" placeholder="Club de Pádel Norte">
                    @error('nombre') <span class="mt-1 block text-sm text-red-700">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="text-sm font-medium">Dirección</span>
                    <span class="mt-1 flex items-center rounded-lg border border-neutral-300 bg-white">
                        <input type="text" wire:model.live.blur="slug" class="w-full rounded-l-lg px-3 py-2" autocapitalize="none" spellcheck="false">
                        <span class="whitespace-nowrap px-3 text-neutral-500">.{{ $dominio }}</span>
                    </span>
                    <span class="mt-1 block text-sm text-neutral-500">Minúsculas, números y guiones. Más adelante puedes usar tu propio dominio.</span>
                    @error('slug') <span class="mt-1 block text-sm text-red-700">{{ $message }}</span> @enderror
                </label>
            </fieldset>

            <fieldset class="space-y-4 rounded-xl border border-neutral-200 bg-white p-6">
                <legend class="px-1 font-medium">Tu cuenta</legend>
                <p class="text-sm text-neutral-500">Serás el propietario: podrás invitar a más gente desde el panel.</p>

                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block">
                        <span class="text-sm font-medium">Tu nombre</span>
                        <input type="text" wire:model="responsable_nombre" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2" autocomplete="name">
                        @error('responsable_nombre') <span class="mt-1 block text-sm text-red-700">{{ $message }}</span> @enderror
                    </label>
                    <label class="block">
                        <span class="text-sm font-medium">Correo electrónico</span>
                        <input type="email" wire:model="email" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2" autocomplete="email">
                        @error('email') <span class="mt-1 block text-sm text-red-700">{{ $message }}</span> @enderror
                    </label>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block">
                        <span class="text-sm font-medium">Contraseña</span>
                        <input type="password" wire:model="clave" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2" autocomplete="new-password">
                        <span class="mt-1 block text-sm text-neutral-500">Al menos 12 caracteres.</span>
                        @error('clave') <span class="mt-1 block text-sm text-red-700">{{ $message }}</span> @enderror
                    </label>
                    <label class="block">
                        <span class="text-sm font-medium">Repite la contraseña</span>
                        <input type="password" wire:model="clave_confirmation" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2" autocomplete="new-password">
                    </label>
                </div>
            </fieldset>

            {{-- Campo trampa: oculto para las personas. --}}
            <div class="hidden" aria-hidden="true">
                <label>Sitio web <input type="text" wire:model="sitio_web" tabindex="-1" autocomplete="off"></label>
            </div>

            <label class="flex items-start gap-3">
                <input type="checkbox" wire:model="privacidad" class="mt-1">
                <span class="text-sm">He leído la <a href="{{ route('legal.privacidad') }}" class="underline" target="_blank">política de privacidad</a> y acepto que mis datos se usen para dar de alta la organización.</span>
            </label>
            @error('privacidad') <span class="block text-sm text-red-700">{{ $message }}</span> @enderror

            <button type="submit" class="rounded-lg px-5 py-2.5 font-medium text-white" style="background: var(--acento)" wire:loading.attr="disabled">
                Crear la organización
            </button>
        </form>
    @endif
</div>
