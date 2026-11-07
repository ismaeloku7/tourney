{{--
    Los horarios del torneo (App\Filament\Resources\Torneos\Pages\HorariosDelTorneo):
    un día por bloque, una fila por pista y cada partido como una barra desde
    su calentamiento (rayado) hasta su final estimado; lo ya empezado, a su
    hora real (de 5 en 5 minutos), con una 🎾 si se está jugando, y una
    línea roja marca la hora de ahora. Con una propuesta,
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
        .hor-linea { position: relative; height: 2.75rem; background: repeating-linear-gradient(135deg, #f3f4f6 0 6px, #e9ebee 6px 12px); border-radius: .375rem; }
        .hor-horas { position: relative; height: 1.25rem; }
        .hor-hora { position: absolute; transform: translateX(-50%); font-size: .6875rem; color: #8a939b; }
        .hor-marca { position: absolute; top: 0; bottom: 0; width: 1px; background: #e6e8eb; }
        .hor-abre { position: absolute; top: 0; bottom: 0; background: #fff; pointer-events: none; }
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
        .hor-herramientas { display: flex; flex-wrap: wrap; gap: .5rem; }
        .hor-marca { pointer-events: none; }
        .hor-guia { position: absolute; top: -.25rem; bottom: -.25rem; width: 2px; background: #1f2328; pointer-events: none; z-index: 3; }
        .hor-guia span { position: absolute; top: -1.15rem; left: 50%; transform: translateX(-50%); background: #1f2328; color: #fff; font-size: .6875rem; font-weight: 700; border-radius: .25rem; padding: 0 .3rem; white-space: nowrap; }
        .hor-suelto { cursor: grab; border-bottom: 1px dashed currentColor; }
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
        .hor-ahora { position: absolute; top: -.25rem; bottom: -.25rem; width: 2px; margin-left: -1px; background: #e03131; pointer-events: none; z-index: 4; }
        .hor-ahora-hora { position: absolute; top: 0; transform: translateX(-50%); background: #e03131; color: #fff; font-size: .6875rem; font-weight: 700; border-radius: .25rem; padding: 0 .3rem; white-space: nowrap; z-index: 4; }
        .hor-bloque.real { box-shadow: inset 0 0 0 1px rgb(255 255 255 / .55); }
        .hor-bloque.enjuego { box-shadow: 0 0 0 2px #e03131; }
        .hor-pelota { display: inline-block; margin-right: .2rem; font-size: .95rem; line-height: 1; filter: drop-shadow(0 0 1px #fff) drop-shadow(0 0 1px #fff); animation: hor-bote 1.2s ease-in-out infinite; }
        @keyframes hor-bote { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-2px); } }
    </style>

    <div class="hor" x-data="{ cogido: null, agarre: 0 }" @if ($refrescar) wire:poll.60s @endif>
        <div class="hor-herramientas">
            {{ $this->calcularAction }}
            {{ $this->soltarHuerfanosAction }}
            {{ $this->recalcularAction }}
            {{ $this->aplicarAction }}
            {{ $this->descartarAction }}
        </div>

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

        @if ($huerfanos > 0)
            <div class="hor-conflictos">
                <strong>{{ $huerfanos === 1 ? '1 partido sigue colocado en una pista que ya no es del torneo' : $huerfanos.' partidos siguen colocados en pistas que ya no son del torneo' }}.</strong>
                <span class="hor-suave">No salen en la parrilla ni se recolocan solos: pulsa «Soltar … de pistas quitadas» arriba y después «Calcular».</span>
            </div>
        @elseif ($sinHora > 0 && ! $esPropuesta)
            <div class="hor-aviso">
                <strong>{{ $sinHora === 1 ? '1 partido sin hora ni pista' : $sinHora.' partidos sin hora ni pista' }}.</strong>
                <span class="hor-suave">Pulsa «Calcular» para colocarlos en las pistas del torneo.</span>
            </div>
        @endif

        @if ($conflictos !== [])
            <div class="hor-conflictos">
                <strong>{{ count($conflictos) === 1 ? '1 partido no cabe' : count($conflictos).' partidos no caben' }}:</strong> <span class="hor-suave">arrástralo a una pista y una hora para colocarlo a mano.</span>
                <ul>
                    @foreach (array_slice($conflictos, 0, 30) as $linea)
                        <li>
                            @if ($linea['movible'])
                                <span
                                    class="hor-suelto"
                                    draggable="true"
                                    title="Arrástralo a una pista y una hora"
                                    x-on:dragstart="cogido = {{ $linea['partido'] }}; agarre = 0; $event.dataTransfer.effectAllowed = 'move'; $event.dataTransfer.setData('text/plain', '{{ $linea['partido'] }}')"
                                    x-on:dragend="cogido = null"
                                >⠿ {{ $linea['texto'] }}</span>
                            @else
                                {{ $linea['texto'] }}
                            @endif
                        </li>
                    @endforeach
                    @if (count($conflictos) > 30)
                        <li>Y {{ count($conflictos) - 30 }} más.</li>
                    @endif
                </ul>
            </div>
        @endif

        @if ($dias !== [])
            <p class="hor-ayuda">La parrilla va más allá del horario de las pistas (la parte rayada): ahí se puede dejar a mano lo que no cabe, para buscarle hueco después. Arrastra un partido a otra pista u hora: la línea marca dónde empezará (se pega al anterior si queda a menos de 15 minutos y empuja a los de detrás). Suéltalo sobre otro partido para intercambiarlos. {{ $esPropuesta ? 'En la propuesta se mueve lo propuesto; se guarda al aplicarla.' : 'Lo jugado o en juego no se mueve.' }}</p>
        @endif

        <div class="hor-leyenda">
            <span><i style="background: repeating-linear-gradient(135deg, #b9c2ca 0 4px, #6d7a84 4px 8px)"></i>Calentamiento</span>
            <span><i style="background: #6d7a84"></i>Partido (duración estimada)</span>
            @if ($esPropuesta)
                <span><i style="background: #6d7a84; opacity: .45"></i>Ya programado (no se toca)</span>
            @endif
            <span><i style="background: #6d7a84; box-shadow: inset 0 0 0 1px rgb(255 255 255 / .55)"></i>Empezado: a su hora real, de 5 en 5 minutos</span>
            <span>🎾 Se está jugando ahora</span>
            <span><i style="width: 2px; background: #e03131"></i>Ahora</span>
            <span><i style="background: #c4c9ce"></i>Pista bloqueada</span>
            <span><i style="background: repeating-linear-gradient(135deg, #f3f4f6 0 3px, #d9dde2 3px 6px)"></i>Fuera del horario de la pista (se puede colocar a mano)</span>
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
                            @if ($dia['ahora'] !== null)
                                <span class="hor-ahora-hora" style="left: {{ $dia['ahora']['izquierda'] }}%">{{ $dia['ahora']['hora'] }}</span>
                            @endif
                        </div>
                    </div>
                    @foreach ($dia['pistas'] as $pista)
                        <div class="hor-fila">
                            <span class="hor-pista" title="{{ $pista['nombre'] }}">{{ $pista['nombre'] }}</span>
                            <div
                                class="hor-linea"
                                x-data="{
                                    encima: false,
                                    minutos: 0,
                                    total: {{ $dia['minutos'] }},
                                    // Donde quedaría el comienzo del partido: el ratón menos por dónde se cogió, de 5 en 5 minutos.
                                    calcular(evento) {
                                        const caja = this.$el.getBoundingClientRect();
                                        this.minutos = Math.max(0, Math.round(((evento.clientX - this.agarre - caja.left) / caja.width) * this.total / 5) * 5);
                                    },
                                    hora() {
                                        const m = {{ $dia['base'] }} + this.minutos;
                                        return String(Math.floor(m / 60) % 24).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0');
                                    },
                                }"
                                x-bind:class="{ destino: encima && cogido !== null }"
                                x-on:dragover.prevent="encima = true; calcular($event)"
                                x-on:dragleave="encima = false"
                                x-on:drop.prevent="
                                    encima = false;
                                    if (cogido === null) return;
                                    calcular($event);
                                    $wire.moverPartido(cogido, {{ $pista['id'] }}, {{ $dia['desde'] }} + minutos * 60);
                                    cogido = null;
                                "
                            >
                                @foreach ($pista['abre'] as $tramo)
                                    <span class="hor-abre" style="left: {{ $tramo['izquierda'] }}%; width: {{ $tramo['ancho'] }}%"></span>
                                @endforeach
                                <div class="hor-guia" x-show="encima && cogido !== null" x-cloak x-bind:style="'left: ' + (minutos / total * 100) + '%'"><span x-text="hora()"></span></div>
                                @foreach ($dia['horas'] as $hora)
                                    <span class="hor-marca" style="left: {{ $hora['izquierda'] }}%"></span>
                                @endforeach
                                @if ($dia['ahora'] !== null)
                                    <span class="hor-ahora" style="left: {{ $dia['ahora']['izquierda'] }}%" title="Ahora: {{ $dia['ahora']['hora'] }}"></span>
                                @endif
                                @foreach ($pista['bloques'] as $bloque)
                                    <div
                                        @class(['hor-bloque', 'nuevo' => $esPropuesta && $bloque['nuevo'], 'viejo' => $esPropuesta && ! $bloque['nuevo'], 'movible' => $bloque['movible'], 'real' => $bloque['real'], 'enjuego' => $bloque['enJuego']])
                                        style="left: {{ $bloque['izquierda'] }}%; width: {{ $bloque['ancho'] }}%; background: {{ $bloque['color'] }}; color: {{ $bloque['color'] }}"
                                        title="{{ $bloque['detalle'] }}"
                                        @if ($bloque['movible'])
                                            draggable="true"
                                            x-data="{ encima: false }"
                                            x-bind:class="{ cogido: cogido === {{ $bloque['partido'] }}, destino: encima && cogido !== null && cogido !== {{ $bloque['partido'] }} }"
                                            x-on:dragstart="cogido = {{ $bloque['partido'] }}; agarre = $event.clientX - $el.getBoundingClientRect().left; $event.dataTransfer.effectAllowed = 'move'; $event.dataTransfer.setData('text/plain', '{{ $bloque['partido'] }}')"
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
                                        <span style="color: #fff">@if ($bloque['enJuego'])<span class="hor-pelota" role="img" aria-label="Se está jugando">🎾</span>@endif<b>{{ $bloque['hora'] }}</b> {{ $bloque['texto'] }}</span>
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
