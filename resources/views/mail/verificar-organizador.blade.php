<x-mail::message>
# Hola, {{ $organizador->responsable_nombre }}:

Has dado de alta **{{ $organizador->nombre }}** en {{ config('app.name') }}. Para activarla, confirma que este correo es tuyo:

<x-mail::button :url="$enlace">
Activar mi organización
</x-mail::button>

El enlace vale {{ $horas }} horas. Después, tu panel estará en {{ $organizador->url('panel') }} y entrarás con este correo y la contraseña que elegiste.

Si no has sido tú, no hagas nada: la organización no se activará.

{{ config('app.name') }}
</x-mail::message>
