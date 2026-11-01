{{-- El PDF de una categoría: fases con grupos (clasificación y partidos) y cuadros por rondas. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $categoria->torneo->nombre }} · {{ $categoria->nombre }}</title>
    @include('exportaciones._estilos')
</head>
<body>
    <div class="pie">{{ $organizador }} · {{ now()->format('d/m/Y H:i') }}</div>
    <h1>{{ $categoria->torneo->nombre }}</h1>
    <p class="suave">{{ $categoria->nombre }} · {{ $categoria->genero->etiqueta() }}{{ $categoria->nivel ? ' · '.$categoria->nivel : '' }} · {{ $organizador }}</p>

    @forelse ($fases as $bloque)
        @php($fase = $bloque['fase'])
        <h2>{{ $fase->nombre }} <span class="suave">({{ \App\Exportaciones\PdfDeCategoria::tipoDeFase($fase) }} · {{ $fase->estado->etiqueta() }})</span></h2>

        @if ($fase->tipo->esDeGrupos())
            @forelse ($fase->grupos as $grupo)
                <div class="bloque">
                    <h3>Grupo {{ $grupo->nombre }}</h3>
                    @if ($grupo->clasificaciones->isNotEmpty())
                        <table>
                            <thead><tr><th class="n">#</th><th>Pareja</th><th class="n">Pts</th><th class="n">PJ</th><th class="n">PG</th><th class="n">PP</th><th class="n">Sets</th><th class="n">Juegos</th><th></th></tr></thead>
                            <tbody>
                                @foreach ($grupo->clasificaciones as $linea)
                                    <tr>
                                        <td class="n">{{ $linea->posicion }}</td>
                                        <td>{{ $linea->participante->nombre }}</td>
                                        <td class="n">{{ $linea->puntos }}</td>
                                        <td class="n">{{ $linea->pj }}</td>
                                        <td class="n">{{ $linea->pg }}</td>
                                        <td class="n">{{ $linea->pp }}</td>
                                        <td class="n">{{ $linea->sets_favor }}-{{ $linea->sets_contra }}</td>
                                        <td class="n">{{ $linea->juegos_favor }}-{{ $linea->juegos_contra }}</td>
                                        <td class="suave">{{ $linea->clasificado_como }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                    @if ($grupo->partidos->isNotEmpty())
                        <table>
                            <thead><tr><th>Jornada</th><th>Partido</th><th>Resultado</th></tr></thead>
                            <tbody>
                                @foreach ($grupo->partidos as $partido)
                                    <tr>
                                        <td>{{ $partido->jornada }}</td>
                                        <td>{{ $partido->local?->nombre }} – {{ $partido->visitante?->nombre }}</td>
                                        <td>{{ $partido->cerrado() ? \App\Filament\Competicion\Marcador::resultado($partido) : $partido->estado->etiqueta() }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            @empty
                <p class="suave">Sin sortear.</p>
            @endforelse
        @else
            @forelse ($bloque['cuadro'] as $ronda)
                <div class="bloque">
                    <h3>{{ $ronda['nombre'] }}</h3>
                    <table>
                        <tbody>
                            @foreach ($ronda['partidos'] as $p)
                                <tr><td>{{ $p['local'] }}</td><td>{{ $p['visitante'] }}</td><td>{{ $p['resultado'] }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @empty
                <p class="suave">El cuadro se llena al cerrar la fase anterior.</p>
            @endforelse
        @endif
    @empty
        <p class="suave">La categoría no tiene fases.</p>
    @endforelse
</body>
</html>
