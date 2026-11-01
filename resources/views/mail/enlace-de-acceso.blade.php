<x-mail::message>
# Hola, {{ $jugador->nombre }}:

Pulsa el botón para entrar en tu cuenta de jugador de {{ $organizador }}:

<x-mail::button :url="$enlace">
Entrar
</x-mail::button>

El enlace vale {{ $minutos }} minutos y una sola vez. Si no lo has pedido tú, no hagas nada.

{{ $organizador }}
</x-mail::message>
