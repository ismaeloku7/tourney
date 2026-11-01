{{-- Los horarios de un torneo, un bloque por día. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $torneo->nombre }} · Horarios</title>
    @include('exportaciones._estilos')
</head>
<body>
    <div class="pie">{{ $organizador }} · {{ now()->format('d/m/Y H:i') }}</div>
    <h1>{{ $torneo->nombre }} · Horarios</h1>
    <p class="suave">{{ $torneo->fecha_inicio->translatedFormat('j \d\e F') }} – {{ $torneo->fecha_fin->translatedFormat('j \d\e F \d\e Y') }} · {{ $organizador }}</p>

    @forelse ($porDia as $dia => $partidos)
        <h2>{{ ucfirst($dia) }}</h2>
        <table>
            <thead><tr><th>Hora</th><th>Pista</th><th>Categoría</th><th>Fase</th><th>Partido</th><th>Resultado</th></tr></thead>
            <tbody>
                @foreach ($partidos as $p)
                    <tr>
                        <td>{{ $p['hora'] }}</td>
                        <td>{{ $p['pista'] }}</td>
                        <td>{{ $p['categoria'] }}</td>
                        <td>{{ $p['fase'] }}</td>
                        <td>{{ $p['partido'] }}</td>
                        <td>{{ $p['resultado'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @empty
        <p class="suave">No hay partidos con los dos participantes decididos.</p>
    @endforelse
</body>
</html>
