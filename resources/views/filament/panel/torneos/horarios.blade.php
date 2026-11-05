{{--
    Los horarios del torneo (App\Filament\Resources\Torneos\Pages\HorariosDelTorneo):
    un día por bloque, una fila por pista y cada partido como una barra desde
    su calentamiento (rayado) hasta su final estimado. Con una propuesta,
    lo nuevo va resaltado y arriba sale el resumen y lo que no cabe.
--}}
<x-filament-panels::page>
    <style>
        .hor { display: flex; flex-direction: column; gap: 1.25rem; }
        .hor-aviso { border: 1px solid #f5b62f; background: #fff8e6; border-radius: .75rem; padding: .75rem 1rem; font-size: .875rem; }
        .hor-aviso strong { display: block; margin-bottom: .25rem; }
        .hor-conflictos { border: 1px solid #f2b8a6; background: #fff3ef; border-radius: .75rem; padding: .75rem 1rem; font-size: .8125rem; }
        .hor-conflictos ul { margin: .25rem 0 0 1rem; list-style: disc; }
        .hor-dia { background: #fff; border: 1px solid #e6e8eb; border-radius: .75rem; padding: 1rem; overflow-x: auto; }
        .hor-dia h3 { font-weight: 700; margin-bottom: .75rem; }
        .hor-rejilla { min-width: 56rem; }
        .hor-fila { display: grid; grid-template-columns: 7rem 1fr; align-items: center; gap: .5rem; }
        .hor-fila + .hor-fila { margin-top: .375rem; }
        .hor-pista { font-size: .8125rem; font-weight: 600; color: #3b4147; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .hor-linea { position: relative; height: 2.75rem; background: repeating-linear-gradient(90deg, #f3f4f6 0 1px, transparent 1px 100%); border-radius: .375rem; background-color: #f8f9fa; }
        .hor-horas { position: relative; height: 1.25rem; }
        .hor-hora { position: absolute; transform: translateX(-50%); font-size: .6875rem; color: #8a939b; }
        .hor-marca { position: absolute; top: 0; bottom: 0; width: 1px; background: #e6e8eb; }
        .hor-bloque { position: absolute; top: .25rem; bottom: .25rem; border-radius: .375rem; color: #fff; font-size: .6875rem; line-height: 1.15; padding: .2rem .35rem; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; box-sizing: border-box; }
        .hor-bloque .hor-cal { position: absolute; left: 0; top: 0; bottom: 0; background: repeating-linear-gradient(135deg, rgb(255 255 255 / .45) 0 4px, transparent 4px 8px); }
        .hor-bloque span { position: relative; }
        .hor-bloque.viejo { opacity: .45; }
        .hor-bloque.nuevo { box-shadow: 0 0 0 2px #fff, 0 0 0 3px currentColor; }
        .hor-leyenda { display: flex; gap: 1rem; font-size: .75rem; color: #8a939b; flex-wrap: wrap; }
        .hor-leyenda i { display: inline-block; width: 1.25rem; height: .75rem; border-radius: .2rem; vertical-align: middle; margin-right: .25rem; }
        .hor-vacio { font-size: .875rem; color: #8a939b; }
    </style>

    <div class="hor">
        @if ($esPropuesta)
            <div class="hor-aviso">
                <strong>Plan propuesto, todavía sin guardar.</strong>
                {{ $resumen ?? 'No hay partidos que colocar.' }}
                Si te convence, pulsa «Aplicar»; si no, cambia las pistas, el calentamiento o el descanso y vuelve a calcular.
            </div>
        @endif

        @if ($conflictos !== [])
            <div class="hor-conflictos">
                <strong>{{ count($conflictos) }} partidos no caben:</strong>
                <ul>
                    @foreach (array_slice($conflictos, 0, 30) as $linea)
                        <li>{{ $linea }}</li>
                    @endforeach
                    @if (count($conflictos) > 30)
                        <li>Y {{ count($conflictos) - 30 }} más.</li>
                    @endif
                </ul>
            </div>
        @endif

        <div class="hor-leyenda">
            <span><i style="background: repeating-linear-gradient(135deg, #b9c2ca 0 4px, #6d7a84 4px 8px)"></i>Calentamiento</span>
            <span><i style="background: #6d7a84"></i>Partido (duración estimada)</span>
            @if ($esPropuesta)
                <span><i style="background: #6d7a84; opacity: .45"></i>Ya programado (no se toca)</span>
            @endif
            <span><i style="background: #c4c9ce"></i>Pista bloqueada</span>
            @foreach ($categorias as $categoria)
                <span><i style="background: {{ $categoria['color'] }}"></i>{{ $categoria['nombre'] }}</span>
            @endforeach
        </div>

        @forelse ($dias as $dia)
            <div class="hor-dia">
                <h3>{{ $dia['titulo'] }}</h3>
                <div class="hor-rejilla">
                    <div class="hor-fila">
                        <span></span>
                        <div class="hor-horas">
                            @foreach ($dia['horas'] as $hora)
                                <span class="hor-hora" style="left: {{ $hora['izquierda'] }}%">{{ $hora['etiqueta'] }}</span>
                            @endforeach
                        </div>
                    </div>
                    @foreach ($dia['pistas'] as $pista)
                        <div class="hor-fila">
                            <span class="hor-pista" title="{{ $pista['nombre'] }}">{{ $pista['nombre'] }}</span>
                            <div class="hor-linea">
                                @foreach ($dia['horas'] as $hora)
                                    <span class="hor-marca" style="left: {{ $hora['izquierda'] }}%"></span>
                                @endforeach
                                @foreach ($pista['bloques'] as $bloque)
                                    <div
                                        @class(['hor-bloque', 'nuevo' => $esPropuesta && $bloque['nuevo'], 'viejo' => $esPropuesta && ! $bloque['nuevo']])
                                        style="left: {{ $bloque['izquierda'] }}%; width: {{ $bloque['ancho'] }}%; background: {{ $bloque['color'] }}; color: {{ $bloque['color'] }}"
                                        title="{{ $bloque['detalle'] }}"
                                    >
                                        <div class="hor-cal" style="width: {{ $bloque['calentamiento'] }}%"></div>
                                        <span style="color: #fff"><b>{{ $bloque['hora'] }}</b> {{ $bloque['texto'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <p class="hor-vacio">Todavía no hay horarios. Añade las pistas del torneo con su disponibilidad y pulsa «Calcular».</p>
        @endforelse
    </div>
</x-filament-panels::page>
