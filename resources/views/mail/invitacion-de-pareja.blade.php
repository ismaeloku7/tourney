<x-mail::message>
# Hola, {{ $invitado->nombre }}:

**{{ $quienInvita->nombre_completo }}** te ha inscrito como su pareja en **{{ $categoria->nombre }}** del torneo **{{ $categoria->torneo->nombre }}**, de {{ $organizador }}.

Para que la inscripción cuente, confírmala:

<x-mail::button :url="$enlace">
Confirmar que juego
</x-mail::button>

El enlace vale {{ $horas }} horas. Si no confirmas, la inscripción no ocupa plaza. Si no conoces a quien te inscribe, no hagas nada.

{{ $organizador }}
</x-mail::message>
