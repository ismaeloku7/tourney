<div class="space-y-6">
    <header>
        <h1 class="text-2xl font-semibold">Avisos</h1>
        <p class="mt-1 text-sm text-tema-suave">Inscripciones, horarios, resultados por validar, cruces y los avisos de la organización.</p>
    </header>

    @include('livewire.jugador._aviso')

    <div class="grid gap-6 lg:grid-cols-[2fr_3fr]">
        <div class="space-y-4">
            <x-pwa.instalar />
            <section class="tarjeta space-y-4 p-4">
                <h2 class="text-base font-semibold">Por dónde quieres los avisos</h2>
                <x-push.activar />
                <form wire:submit="guardarAvisos" class="space-y-3 text-sm">
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="avisosCorreo"> Por correo</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="avisosPush"> En el móvil (push)</label>
                    <button type="submit" class="boton">Guardar</button>
                </form>
            </section>
        </div>

        <section>
            <h2 class="text-base font-semibold">Los últimos avisos</h2>
            @if ($historial->isEmpty())
                <p class="mt-2 text-sm text-tema-suave">Todavía no te hemos enviado ninguno.</p>
            @else
                <ul class="mt-3 divide-y divide-tema-borde tarjeta text-sm">
                    @foreach ($historial as $notificacion)
                        <li class="flex justify-between gap-3 px-4 py-2.5">
                            <a href="{{ $notificacion->data['url'] ?? '/' }}" class="min-w-0">
                                <span class="block font-medium">{{ $notificacion->data['titulo'] ?? '' }}</span>
                                <span class="block truncate text-tema-suave">{{ $notificacion->data['texto'] ?? '' }}</span>
                            </a>
                            <span class="shrink-0 text-xs text-tema-suave">{{ $notificacion->created_at?->diffForHumans() }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
</div>
