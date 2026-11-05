{{-- Las copias de seguridad de la organización (App\Filament\Pages\CopiasDeSeguridad). --}}
<x-filament-panels::page>
    <div class="esc-tarjeta p-4 text-sm text-gray-600 dark:text-gray-300">
        <p>Cada noche, a las 04:10, se hace sola una copia de la base de datos y de los archivos subidos. Aquí están las últimas {{ $conservar }}; las más antiguas se borran solas. Cada copia es un zip con <code>base.sql</code> (se restaura en cualquier MySQL o SQLite) y la carpeta <code>archivos</code>.</p>
        <p class="mt-2">Descárgalas de vez en cuando y guárdalas fuera del servidor: una copia que vive en la misma máquina que los datos no protege de que la máquina se pierda.</p>
    </div>

    @if ($copias === [])
        <p class="text-sm text-gray-500 dark:text-gray-400">Todavía no hay ninguna copia. Haz la primera con el botón de arriba.</p>
    @else
        <div class="esc-tarjeta overflow-hidden">
            <table class="w-full text-sm">
                <thead class="text-left text-xs text-gray-500 dark:text-gray-400">
                    <tr><th class="px-4 py-2 font-medium">Copia</th><th class="px-4 py-2 font-medium">Fecha</th><th class="px-4 py-2 text-right font-medium">Tamaño</th><th class="px-4 py-2"></th></tr>
                </thead>
                <tbody>
                    @foreach ($copias as $copia)
                        <tr class="border-t border-gray-100 dark:border-white/5" wire:key="copia-{{ $copia['nombre'] }}">
                            <td class="px-4 py-2 font-medium">{{ $copia['nombre'] }}</td>
                            <td class="px-4 py-2">{{ $copia['fecha']->translatedFormat('D j M Y, H:i') }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ \Illuminate\Support\Number::fileSize($copia['tamano'], precision: 1) }}</td>
                            <td class="px-4 py-2 text-right">{{ ($this->descargarAction)(['archivo' => $copia['nombre']]) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-filament-panels::page>
