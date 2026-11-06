{{--
    El esquema de un cuadro antes de jugarse (App\Competicion\EsquemaDeCuadro),
    en el formulario de la fase: una columna por ronda con sus cruces y, en
    cada hueco, quién llega. Se actualiza al cambiar la configuración.
--}}
<div class="esq">
    <style>
        .esq { width: 100%; }
        .esq-aviso { font-size: .8125rem; color: #8a939b; margin-bottom: .5rem; }
        .esq-error { font-size: .8125rem; color: #b42318; }
        .esq-cuadro { display: flex; gap: 1.25rem; overflow-x: auto; padding-bottom: .5rem; }
        .esq-ronda { display: flex; flex-direction: column; min-width: 10.5rem; }
        .esq-ronda h4 { font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #8a939b; margin-bottom: .5rem; }
        .esq-partidos { display: flex; flex-direction: column; justify-content: space-around; flex: 1; gap: .5rem; }
        .esq-partido { border: 1px solid #d0d5dd; border-radius: .5rem; background: #fff; font-size: .8125rem; overflow: hidden; position: relative; }
        .esq-partido .esq-codigo { position: absolute; top: .2rem; right: .35rem; font-size: .625rem; font-weight: 700; color: #8a939b; }
        .esq-hueco { padding: .3rem .5rem; color: #1f2328; font-weight: 600; white-space: nowrap; }
        .esq-hueco + .esq-hueco { border-top: 1px solid #eaecf0; }
        .esq-hueco.bye { color: #98a2b3; font-weight: 400; font-style: italic; }
        .esq-hueco.viene { color: #667085; font-weight: 400; }
        .dark .esq-partido { background: #1f2328; border-color: #3b4147; }
        .dark .esq-hueco { color: #f2f4f7; }
        .dark .esq-hueco + .esq-hueco { border-top-color: #3b4147; }
    </style>

    @if ($error !== null)
        <p class="esq-error">{{ $error }}</p>
    @else
        @if ($aviso !== null)
            <p class="esq-aviso">{{ $aviso }}</p>
        @endif
        <div class="esq-cuadro">
            @foreach ($rondas as $ronda)
                <div class="esq-ronda">
                    <h4>{{ $ronda['nombre'] }}</h4>
                    <div class="esq-partidos">
                        @foreach ($ronda['partidos'] as $partido)
                            <div class="esq-partido">
                                <span class="esq-codigo">{{ $partido['codigo'] }}</span>
                                <div @class(['esq-hueco', 'bye' => $partido['localBye'], 'viene' => str_starts_with($partido['local'], 'Ganador') || str_starts_with($partido['local'], 'Perdedor')])>{{ $partido['local'] }}</div>
                                <div @class(['esq-hueco', 'bye' => $partido['visitanteBye'], 'viene' => str_starts_with($partido['visitante'], 'Ganador') || str_starts_with($partido['visitante'], 'Perdedor')])>{{ $partido['visitante'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
            @if ($tercero !== null)
                <div class="esq-ronda">
                    <h4>Tercer puesto</h4>
                    <div class="esq-partidos">
                        <div class="esq-partido">
                            <div class="esq-hueco viene">{{ $tercero['local'] }}</div>
                            <div class="esq-hueco viene">{{ $tercero['visitante'] }}</div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>
