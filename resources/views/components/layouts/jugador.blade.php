{{--
    El portal del jugador: la superficie pública con un menú propio debajo
    de la cabecera (Inicio, Partidos, Avisos, Mis datos) y el botón de
    salir. Cada entrada es una ruta, no una pestaña.
--}}
@php($entradas = [
    'jugador.portal' => 'Inicio',
    'jugador.partidos' => 'Partidos',
    'jugador.avisos' => 'Avisos',
    'jugador.datos' => 'Mis datos',
])
<x-layouts.publico :titulo="$titulo ?? $title ?? null">
    <nav class="border-b border-tema-borde bg-white" aria-label="Portal del jugador">
        <div class="mx-auto flex max-w-5xl items-center justify-between gap-3 px-4">
            <div class="flex gap-1 overflow-x-auto py-2 text-sm">
                @foreach ($entradas as $ruta => $nombre)
                    <a href="{{ route($ruta) }}" @class(['whitespace-nowrap rounded-full px-3.5 py-1.5 font-medium transition', 'bg-tema-fondo text-tema-texto' => request()->routeIs($ruta) || ($ruta === 'jugador.portal' && request()->routeIs('jugador.inscripcion')), 'text-tema-suave hover:text-tema-texto' => ! (request()->routeIs($ruta) || ($ruta === 'jugador.portal' && request()->routeIs('jugador.inscripcion')))])>{{ $nombre }}</a>
                @endforeach
            </div>
            <form method="POST" action="{{ route('jugador.salir') }}" class="shrink-0">
                @csrf
                <button type="submit" class="rounded-full px-3 py-1.5 text-sm text-tema-suave hover:text-tema-texto">Salir</button>
            </form>
        </div>
    </nav>
    <div class="mx-auto max-w-5xl px-4 py-6 sm:py-8">
        {{ $slot }}
    </div>
</x-layouts.publico>
