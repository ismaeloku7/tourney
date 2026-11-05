{{-- La clasificación y los partidos de un grupo. Espera $g (del bloque de DatosDelTorneo, con 'cruces') y $miParticipante. --}}
<table class="w-full text-sm">
    <thead class="text-left text-tema-suave"><tr><th class="py-1 font-medium">#</th><th class="font-medium">Pareja</th><th class="text-right font-medium">Pts</th><th class="text-right font-medium">PJ</th><th class="text-right font-medium">Sets</th><th class="text-right font-medium">Juegos</th>@if ($g['cruces'])<th class="text-right font-medium">Cruce</th>@endif</tr></thead>
    <tbody>
        @foreach ($g['lineas'] as $l)
            <tr @class(['border-t border-tema-borde', 'font-semibold' => $l['clasificado'], 'bg-blue-50/60' => $l['participante'] === $miParticipante])>
                <td class="py-1.5">{{ $l['posicion'] }}</td>
                <td>{{ $l['nombre'] }}@if ($l['participante'] === $miParticipante) <span class="text-xs font-normal text-tema-azul">tú</span>@endif</td>
                <td class="text-right tabular-nums">{{ $l['puntos'] }}</td>
                <td class="text-right tabular-nums">{{ $l['pj'] }}</td>
                <td class="text-right tabular-nums">{{ $l['sets'] }}</td>
                <td class="text-right tabular-nums">{{ $l['juegos'] }}</td>
                @if ($g['cruces'])<td class="whitespace-nowrap text-right text-tema-suave">{{ $l['cruce'] !== null ? 'vs '.$l['cruce'] : '' }}</td>@endif
            </tr>
        @endforeach
    </tbody>
</table>
@if ($g['partidos'] !== [])
    <ul class="mt-3 space-y-1 border-t border-tema-borde pt-3 text-sm">
        @foreach ($g['partidos'] as $p)
            <li class="flex justify-between gap-3"><span>{{ $p['partido'] }}</span><span class="whitespace-nowrap text-tema-suave">{{ $p['resultado'] ?: ($p['hora'] ?? '') }}</span></li>
        @endforeach
    </ul>
@endif
