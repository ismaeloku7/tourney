{{--
    Superficie pública: la de la plataforma en su dominio (landing, alta,
    legales) y la de un organizador en su host. Sirve de layout de Livewire
    ($slot) y de componente <x-publico>.
--}}
@php($organizador = tenancy()->initialized ? tenant() : null)
@php($color = $organizador ? \App\Filament\ColorDelPanel::hex() : '#4cc17d')
@php($logo = $organizador ? \App\Filament\MarcaDelOrganizador::urlDelLogo() : null)
@php($nombre = $organizador ? \App\Filament\MarcaDelOrganizador::nombre() : config('app.name'))
@php($torneoDeLaRuta = request()->route('torneo'))
@php($titulo = $titulo ?? $title ?? ($torneoDeLaRuta instanceof \App\Models\Torneo ? $torneoDeLaRuta->nombre : null))
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ isset($titulo) && $titulo ? $titulo.' · ' : '' }}{{ $nombre }}</title>
        <meta name="theme-color" content="{{ $color }}">
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
        @if ($logo)
            <link rel="icon" href="{{ $logo }}">
        @endif
        @if ($organizador)
            {{-- La aplicación instalable del organizador (manifiesto, iconos y service worker). --}}
            <link rel="manifest" href="/manifest.webmanifest">
            <link rel="apple-touch-icon" href="/icono-apple.png?v={{ \App\Publico\IconosDelOrganizador::version() }}">
            <meta name="apple-mobile-web-app-capable" content="yes">
            <meta name="apple-mobile-web-app-title" content="{{ $nombre }}">
            <script>if ('serviceWorker' in navigator) { window.addEventListener('load', function () { navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(function () {}); }); }</script>
        @endif
        @livewireStyles
    </head>
    <body class="flex min-h-screen flex-col bg-tema-fondo text-tema-texto antialiased" style="--acento: {{ $color }}">
        <header class="border-b border-tema-borde bg-white">
            <div class="mx-auto flex max-w-5xl items-center justify-between gap-3 px-4 py-3">
                <a href="{{ url('/') }}" class="flex min-w-0 items-center gap-3">
                    @if ($logo)
                        <img src="{{ $logo }}" alt="" class="h-10 w-auto max-w-40 object-contain">
                    @else
                        <span class="flex size-9 items-center justify-center rounded-[3px] text-base font-bold text-white" style="background: var(--acento)" aria-hidden="true">{{ mb_strtoupper(mb_substr($nombre, 0, 1)) }}</span>
                    @endif
                    <span class="truncate text-sm font-medium">{{ $nombre }}</span>
                </a>
                <nav class="flex items-center gap-1 text-sm">
                    @if ($organizador)
                        @if (\Illuminate\Support\Facades\Auth::guard('jugador')->check())
                            <a href="{{ route('jugador.portal') }}" class="boton px-4 py-1.5">Mis torneos</a>
                        @else
                            <a href="{{ route('jugador.acceso') }}" class="boton px-4 py-1.5">Soy jugador</a>
                        @endif
                        <a href="{{ url('/panel') }}" class="rounded-full px-3 py-1.5 text-tema-suave hover:text-tema-texto">Equipo</a>
                    @else
                        <a href="{{ route('alta') }}" class="boton px-4 py-1.5">Crear mi organización</a>
                    @endif
                </nav>
            </div>
        </header>

        <main class="flex-1">
            {{ $slot }}
        </main>

        <footer class="border-t border-tema-borde bg-white">
            <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3 px-4 py-4 text-xs text-tema-suave">
                <span>{{ $nombre }}@if ($organizador && ! $organizador->plan->sin_marca) · con {{ config('app.name') }}@endif</span>
                <span class="flex gap-4">
                    <a href="{{ route('legal.aviso') }}" class="hover:text-neutral-900">Aviso legal</a>
                    <a href="{{ route('legal.privacidad') }}" class="hover:text-neutral-900">Privacidad</a>
                </span>
            </div>
        </footer>
        @livewireScripts
    </body>
</html>
