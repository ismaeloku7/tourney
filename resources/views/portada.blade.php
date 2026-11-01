<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name') }}</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="min-h-screen bg-white text-neutral-900 antialiased">
        <main class="mx-auto flex min-h-screen max-w-2xl flex-col justify-center px-6 py-16">
            <h1 class="text-3xl font-semibold">{{ config('app.name') }}</h1>
            <p class="mt-4 text-lg text-neutral-600">
                {{ __('Gestión de torneos de pádel: inscripción online, cuadros, horarios y resultados en vivo.') }}
            </p>
            <p class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('alta') }}" class="rounded-lg bg-blue-700 px-5 py-2.5 font-medium text-white hover:bg-blue-800">Crear mi organización</a>
            </p>
            <p class="mt-12 flex gap-4 text-sm text-neutral-500">
                <a href="{{ route('legal.aviso') }}" class="hover:text-neutral-900">Aviso legal</a>
                <a href="{{ route('legal.privacidad') }}" class="hover:text-neutral-900">Privacidad</a>
            </p>
        </main>
    </body>
</html>
