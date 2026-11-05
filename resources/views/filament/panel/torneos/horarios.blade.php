{{--
    Los horarios del torneo (App\Filament\Resources\Torneos\Pages\HorariosDelTorneo):
    un día por bloque, una fila por pista y cada partido como una barra desde
    su calentamiento (rayado) hasta su final estimado. Con una propuesta,
    lo nuevo va resaltado y arriba sale el resumen y lo que no cabe.
    Los partidos se arrastran: a otra pista u hora (moverPartido) o sobre
    otro partido para intercambiarlos (intercambiarPartidos).
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
        .hor-bloque.movible { cursor: grab; }
        .hor-bloque.cogido { opacity: .35; }
        .hor-bloque.destino { outline: 2px dashed #1f2328; outline-offset: 1px; }
        .hor-linea.destino { background-color: #eef4ff; }
        .hor-ayuda { font-size: .75rem; color: #8a939b; }
        .hor-alerta { position: absolute; top: .15rem; right: .15rem; width: .9rem; height: .9rem; border-radius: 999px; background: #fff; color: #d9480f; font-weight: 800; font-size: .625rem; line-height: .9rem; text-align: center; pointer-events: none; }
        .hor-leyenda { display: flex; gap: 1rem; font-size: .75rem; color: #8a939b; flex-wrap: wrap; }
        .hor-leyenda i { display: inline-block; width: 1.25rem; height: .75rem; border-radius: .2rem; vertical-align: middle; margin-right: .25rem; }
        .hor-vacio { font-size: .875rem; color: #8a939b; }
        .hor-directo { width: 100%; font-size: .8125rem; border-collapse: collapse; }
        .hor-directo th { text-align: left; color: #8a939b; font-weight: 600; padding: .25rem .5rem; border-bottom: 1px solid #e6e8eb; }
        .hor-directo td { padding: .4rem .5rem; border-bottom: 1px solid #f3f4f6; }
        .hor-suave { color: #8a939b; }
        .hor-tarde { color: #d9480f; font-weight: 700; }
        .hor-bien { color: #2f9e44; }
    </style>

    <div class="hor" x-data="{ cogido: null }">
        @if ($directo !== null)
            <div class="hor-dia">
                <h3>En directo</h3>
                @if ($sugerencia !== null)
                    <div class="hor-aviso" style="margin-bottom: .75rem">
                        <strong>{{ $sugerencia['texto'] }}</strong>
                        {{ $sugerencia['ganancia'] }}
                        <div style="margin-top: .5rem; display: flex; gap: .5rem">
                            <x-filament::button size="sm" color="success" wire:click="aplicarSugerencia('{{ $sugerencia['clave'] }}')" wire:confirm="Se cambia el partido de pista y se avisa a sus parejas y a las que cambian de hora. ¿Seguimos?">Aplicar y avisar</x-filament::button>
                            <x-filament::button size="sm" color="gray" wire:click="ignorarSugerencia('{{ $sugerencia['clave'] }}')">Ignorar</x-filament::button>
                        </div>
                    </div>
                @endif
                <table class="hor-directo">
                    <thead><tr><th>Pista</th><th>Va</th><th>En pista</th><th>Siguiente</th><th>Quedan</th></tr></thead>
                    <tbody>
                        @foreach ($directo as $fila)
                            <tr>
                                <td><strong>{{ $fila['pista'] }}</strong></td>
                                <td>
                                    @if ($fila['retraso'] === null) <span class="hor-suave">—</span>
                                    @elseif ($fila['retraso'] >= $retrasoMinimo) <span class="hor-tarde">{{ $fila['retraso'] }} min tarde</span>
                                    @elseif ($fila['retraso'] > 0) {{ $fila['retraso'] }} min tarde
                                    @elseif ($fila['retraso'] < 0) <span class="hor-bien">{{ -$fila['retraso'] }} min antes</span>
                                    @else <span class="hor-bien">en hora</span>
                                    @endif
                                </td>
                                <td>{{ $fila['enPista'] ?? '—' }}</td>
                                <td>{!! $fila['siguiente'] !== null ? e($fila['siguiente']).' <span class="hor-suave">hacia las '.e($fila['hora']).'</span>' : '—' !!}</td>
                                <td>{{ $fila['quedan'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="hor-suave" style="margin-top: .5rem">Tras cada resultado se recalculan las horas de lo que falta y se avisa a la pista: «a calentar» a la siguiente pareja y «sois los siguientes» a la de después.</p>
            </div>
        @endif

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

        @if ($dias !== [])
            <p class="hor-ayuda">Arrastra un partido a otra pista u hora (se pega al anterior si queda cerca y empuja a los de detrás); suéltalo sobre otro partido para intercambiarlos. {{ $esPropuesta ? 'En la propuesta se mueve lo propuesto; se guarda al aplicarla.' : 'Lo jugado o en juego no se mueve.' }}</p>
        @endif

        <div class="hor-leyenda">
            <span><i style="background: repeating-linear-gradient(135deg, #b9c2ca 0 4px, #6d7a84 4px 8px)"></i>Calentamiento</span>
            <span><i style="background: #6d7a84"></i>Partido (duración estimada)</span>
            @if ($esPropuesta)
                <span><i style="background: #6d7a84; opacity: .45"></i>Ya programado (no se toca)</span>
            @endif
            <span><i style="background: #c4c9ce"></i>Pista bloqueada</span>
            <span><i style="background: #6d7a84; position: relative"><b style="position: absolute; top: -.2rem; right: -.35rem; width: .75rem; height: .75rem; border-radius: 999px; background: #fff; color: #d9480f; font-size: .55rem; line-height: .75rem; text-align: center; box-shadow: 0 0 0 1px #e6e8eb">!</b></i>Tercer partido seguido de alguien</span>
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
                            <div
                                class="hor-linea"
                                x-data="{ encima: false }"
                                x-bind:class="{ destino: encima && cogido !== null }"
                                x-on:dragover.prevent="encima = true"
                                x-on:dragleave="encima = false"
                                x-on:drop.prevent="
                                    encima = false;
                                    if (cogido === null) return;
                                    const caja = $el.getBoundingClientRect();
                                    const minutos = Math.max(0, Math.round((($event.clientX - caja.left) / caja.width) * {{ $dia['minutos'] }} / 5) * 5);
                                    $wire.moverPartido(cogido, {{ $pista['id'] }}, {{ $dia['desde'] }} + minutos * 60);
                                    cogido = null;
                                "
                            >
                                @foreach ($dia['horas'] as $hora)
                                    <span class="hor-marca" style="left: {{ $hora['izquierda'] }}%"></span>
                                @endforeach
                                @foreach ($pista['bloques'] as $bloque)
                                    <div
                                        @class(['hor-bloque', 'nuevo' => $esPropuesta && $bloque['nuevo'], 'viejo' => $esPropuesta && ! $bloque['nuevo'], 'movible' => $bloque['movible']])
                                        style="left: {{ $bloque['izquierda'] }}%; width: {{ $bloque['ancho'] }}%; background: {{ $bloque['color'] }}; color: {{ $bloque['color'] }}"
                                        title="{{ $bloque['detalle'] }}"
                                        @if ($bloque['movible'])
                                            draggable="true"
                                            x-data="{ encima: false }"
                                            x-bind:class="{ cogido: cogido === {{ $bloque['partido'] }}, destino: encima && cogido !== null && cogido !== {{ $bloque['partido'] }} }"
                                            x-on:dragstart="cogido = {{ $bloque['partido'] }}; $event.dataTransfer.effectAllowed = 'move'; $event.dataTransfer.setData('text/plain', '{{ $bloque['partido'] }}')"
                                            x-on:dragend="cogido = null"
                                            x-on:dragover.prevent.stop="encima = true"
                                            x-on:dragleave="encima = false"
                                            x-on:drop.prevent.stop="
                                                encima = false;
                                                if (cogido !== null && cogido !== {{ $bloque['partido'] }}) $wire.intercambiarPartidos(cogido, {{ $bloque['partido'] }});
                                                cogido = null;
                                            "
                                        @endif
                                    >
                                        <div class="hor-cal" style="width: {{ $bloque['calentamiento'] }}%"></div>
                                        <span style="color: #fff"><b>{{ $bloque['hora'] }}</b> {{ $bloque['texto'] }}</span>
                                        @if ($bloque['tercero'])
                                            <span class="hor-alerta" aria-label="Alguien juega su tercer partido seguido">!</span>
                                        @endif
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
