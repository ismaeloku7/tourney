{{--
    El cuadro de una eliminatoria: una columna por ronda, con cada partido
    (quién, de dónde vendrá si aún no se sabe, y el resultado). El tercer
    puesto va aparte, debajo de la final.
--}}
@php
    use App\Filament\Competicion\Marcador;

    /** @var \App\Models\Fase $fase */
    $partidos = $fase->partidos()->with(['local', 'visitante', 'ganador', 'sets', 'franja', 'pista'])->orderBy('ronda')->orderBy('posicion')->get();
    $rondas = (int) $partidos->where('es_tercer_puesto', false)->max('ronda');
    $porRonda = $partidos->where('es_tercer_puesto', false)->groupBy('ronda');
    $tercero = $partidos->firstWhere('es_tercer_puesto', true);
@endphp
<div class="cuadro">
    <style>
        .cuadro { display: flex; gap: 1rem; overflow-x: auto; padding-bottom: .5rem; }
        .cuadro .ronda { min-width: 15rem; flex: 1; display: flex; flex-direction: column; justify-content: space-around; gap: .75rem; }
        .cuadro h3 { font-weight: 700; font-size: .875rem; text-align: center; }
        .cuadro .partido { border: 1px solid rgb(229 231 235 / .7); border-radius: .5rem; padding: .5rem .75rem; font-size: .875rem; }
        .cuadro .lado { display: flex; justify-content: space-between; gap: .5rem; }
        .cuadro .gana { font-weight: 700; }
        .cuadro .suave { color: rgb(107 114 128); font-size: .75rem; }
    </style>
    @forelse ($porRonda as $ronda => $delaRonda)
        <div class="ronda">
            <h3>{{ Marcador::ronda((int) $ronda, $rondas) }}</h3>
            @foreach ($delaRonda as $partido)
                <div class="partido">
                    <div class="lado"><span @class(['gana' => $partido->ganador_id !== null && $partido->ganador_id === $partido->local_id])>{{ Marcador::lado($partido, true) }}</span></div>
                    <div class="lado"><span @class(['gana' => $partido->ganador_id !== null && $partido->ganador_id === $partido->visitante_id])>{{ Marcador::lado($partido, false) }}</span></div>
                    <div class="suave">
                        P{{ $partido->id }} ·
                        @if ($partido->cerrado()) {{ Marcador::resultado($partido) }}
                        @elseif ($partido->franja !== null) {{ $partido->franja->inicio->translatedFormat('D j/n H:i') }}{{ $partido->pista !== null ? ' · '.$partido->pista->nombre : '' }}
                        @else {{ $partido->estado->etiqueta() }}
                        @endif
                    </div>
                </div>
            @endforeach
            @if ((int) $ronda === $rondas && $tercero !== null)
                <h3>Tercer puesto</h3>
                <div class="partido">
                    <div class="lado"><span @class(['gana' => $tercero->ganador_id !== null && $tercero->ganador_id === $tercero->local_id])>{{ Marcador::lado($tercero, true) }}</span></div>
                    <div class="lado"><span @class(['gana' => $tercero->ganador_id !== null && $tercero->ganador_id === $tercero->visitante_id])>{{ Marcador::lado($tercero, false) }}</span></div>
                    <div class="suave">P{{ $tercero->id }} · {{ $tercero->cerrado() ? Marcador::resultado($tercero) : $tercero->estado->etiqueta() }}</div>
                </div>
            @endif
        </div>
    @empty
        <p class="suave">El cuadro se llena al cerrar la fase anterior.</p>
    @endforelse
</div>
