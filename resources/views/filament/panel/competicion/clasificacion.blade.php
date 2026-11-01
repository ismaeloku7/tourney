{{--
    La clasificación de una fase de grupos, un grupo por tabla: posición,
    pareja, puntos, partidos, sets y juegos, y por qué desempató. Los
    clasificados van marcados.
--}}
@php
    /** @var \App\Models\Fase $fase */
    $grupos = $fase->grupos()->with(['clasificaciones.participante', 'participantes'])->get();
@endphp
<div class="clasificacion">
    <style>
        .clasificacion { display: grid; gap: 1.25rem; }
        .clasificacion h3 { font-weight: 700; margin-bottom: .5rem; }
        .clasificacion table { width: 100%; font-size: .875rem; border-collapse: collapse; }
        .clasificacion th { text-align: left; font-weight: 600; color: rgb(107 114 128); padding: .25rem .5rem; border-bottom: 1px solid rgb(229 231 235 / .6); }
        .clasificacion td { padding: .375rem .5rem; border-bottom: 1px solid rgb(229 231 235 / .4); }
        .clasificacion td.n, .clasificacion th.n { text-align: right; font-variant-numeric: tabular-nums; }
        .clasificacion .clasificado td { font-weight: 600; }
        .clasificacion .suave { color: rgb(107 114 128); font-size: .75rem; }
    </style>
    @forelse ($grupos as $grupo)
        <section>
            <h3>Grupo {{ $grupo->nombre }}</h3>
            @if ($grupo->clasificaciones->isEmpty())
                <p class="suave">Sin partidos todavía.</p>
            @else
                <table>
                    <thead>
                        <tr>
                            <th class="n">#</th><th>Pareja</th><th class="n">Pts</th><th class="n">PJ</th><th class="n">PG</th><th class="n">PP</th><th class="n">Sets</th><th class="n">Juegos</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($grupo->clasificaciones as $linea)
                            <tr @class(['clasificado' => $linea->clasificado_como !== null])>
                                <td class="n">{{ $linea->posicion }}</td>
                                <td>{{ $linea->participante->nombre }}</td>
                                <td class="n">{{ $linea->puntos }}</td>
                                <td class="n">{{ $linea->pj }}</td>
                                <td class="n">{{ $linea->pg }}</td>
                                <td class="n">{{ $linea->pp }}</td>
                                <td class="n">{{ $linea->sets_favor }}-{{ $linea->sets_contra }}</td>
                                <td class="n">{{ $linea->juegos_favor }}-{{ $linea->juegos_contra }}</td>
                                <td class="suave">
                                    @if ($linea->clasificado_como !== null) {{ $linea->clasificado_como }} @endif
                                    @if ($linea->desempate_aplicado !== null) · {{ $linea->desempate_aplicado->etiqueta() }} @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    @empty
        <p class="suave">La fase aún no se ha sorteado.</p>
    @endforelse
</div>
