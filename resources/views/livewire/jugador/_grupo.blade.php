{{-- La clasificación y los partidos de un grupo. Espera $g (del bloque de DatosDelTorneo, con 'cruces') y $miParticipante. --}}
<table class="w-full text-[0.9375rem]">
    <thead class="text-left text-xs uppercase tracking-[0.08em] text-tema-suave"><tr><th class="py-1.5 font-semibold">#</th><th class="font-semibold">Equipo</th><th class="text-right font-semibold">Pts</th><th class="text-right font-semibold">PJ</th><th class="hidden text-right font-semibold sm:table-cell">Sets</th><th class="hidden text-right font-semibold sm:table-cell">Juegos</th>@if ($g['cruces'])<th class="text-right font-semibold">Cruce</th>@endif</tr></thead>
    <tbody>
        @foreach ($g['lineas'] as $l)
            <tr @class(['border-t border-tema-borde', 'font-semibold' => $l['clasificado'], 'bg-blue-50/60' => $l['participante'] === $miParticipante])>
                <td class="py-2 text-tema-suave">{{ $l['posicion'] }}</td>
                <td class="font-medium">{{ $l['nombre'] }}@if ($l['participante'] === $miParticipante) <span class="ml-1 rounded-full bg-tema-azul px-1.5 py-0.5 text-[0.6875rem] font-semibold text-white">tú</span>@endif</td>
                <td class="text-right text-base font-semibold tabular-nums">{{ $l['puntos'] }}</td>
                <td class="text-right tabular-nums">{{ $l['pj'] }}</td>
                <td class="hidden text-right tabular-nums sm:table-cell">{{ $l['sets'] }}</td>
                <td class="hidden text-right tabular-nums sm:table-cell">{{ $l['juegos'] }}</td>
                @if ($g['cruces'])<td class="whitespace-nowrap text-right text-tema-suave">{{ $l['cruce'] !== null ? 'vs '.$l['cruce'] : '' }}</td>@endif
            </tr>
        @endforeach
    </tbody>
</table>
@if ($g['partidos'] !== [])
    <h3 class="mt-4 border-t border-tema-borde pt-3 text-xs font-semibold uppercase tracking-[0.08em] text-tema-suave">Partidos del grupo</h3>
    <ul class="mt-2 divide-y divide-tema-borde text-sm">
        @foreach ($g['partidos'] as $p)
            <li class="flex justify-between gap-3 py-1.5"><span>{{ $p['partido'] }}</span><span @class(['whitespace-nowrap tabular-nums', 'font-semibold' => $p['resultado'] !== '', 'text-tema-suave' => $p['resultado'] === ''])>{{ $p['resultado'] ?: ($p['enVivo'] !== '' ? 'va '.$p['enVivo'] : ($p['hora'] ?? 'Por jugar')) }}</span></li>
        @endforeach
    </ul>
@endif
