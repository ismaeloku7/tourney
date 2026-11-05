{{-- La píldora de estado de una inscripción. Espera $inscripcion. --}}
<span @class([
    'shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium',
    'bg-green-50 text-green-800' => $inscripcion->estado === \App\Enums\EstadoInscripcion::Confirmada,
    'bg-amber-50 text-amber-800' => $inscripcion->estado === \App\Enums\EstadoInscripcion::Pendiente,
    'bg-blue-50 text-blue-800' => $inscripcion->estado === \App\Enums\EstadoInscripcion::ListaDeEspera,
    'bg-tema-fondo text-tema-suave' => ! in_array($inscripcion->estado, [\App\Enums\EstadoInscripcion::Confirmada, \App\Enums\EstadoInscripcion::Pendiente, \App\Enums\EstadoInscripcion::ListaDeEspera], true),
])>{{ $inscripcion->estado->etiqueta() }}@if ($inscripcion->posicion_espera) (#{{ $inscripcion->posicion_espera }})@endif</span>
