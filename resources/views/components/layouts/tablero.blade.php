{{-- Pantalla completa para la tele del club: fondo oscuro, letra grande, sin cabecera ni pie. --}}
@php($color = \App\Filament\ColorDelPanel::hex())
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @php($torneoDeLaRuta = request()->route('torneo'))
        <title>{{ $torneoDeLaRuta instanceof \App\Models\Torneo ? 'Tablero · '.$torneoDeLaRuta->nombre : config('app.name') }}</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
        <link rel="manifest" href="/manifest.webmanifest">
        @livewireStyles
    </head>
    <body class="min-h-screen bg-neutral-950 text-white antialiased" style="--acento: {{ $color }}">
        {{ $slot }}
        @livewireScripts
    </body>
</html>
