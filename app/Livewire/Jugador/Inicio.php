<?php

namespace App\Livewire\Jugador;

use App\Jugadores\PartidosDelJugador;
use App\Jugadores\ResumenDelJugador;
use App\Models\Inscripcion;
use App\Models\Partido;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;

/**
 * El inicio del portal: un saludo, cuatro cifras, lo que tiene pendiente,
 * su próximo partido y sus torneos con la pareja de cada uno.
 */
#[Layout('components.layouts.jugador')]
class Inicio extends PaginaDelJugador
{
    public function render(ResumenDelJugador $resumen, PartidosDelJugador $partidos): View
    {
        $yo = $this->yo();
        $inscripciones = $resumen->inscripciones($yo);
        $porJugar = $partidos->todos($yo)->filter(fn (Partido $p): bool => $partidos->porJugar($p))->values();
        $proximo = $porJugar->first();
        $masProxima = $resumen->inscripcionMasProxima($yo);

        return view('livewire.jugador.inicio', [
            'yo' => $yo,
            'inscripciones' => $inscripciones,
            'activas' => $resumen->activas($yo)->count(),
            'proximo' => $proximo === null ? null : $partidos->fila($proximo, $yo),
            'siguientes' => $porJugar->slice(1, 4)->map(fn (Partido $p): array => $partidos->fila($p, $yo))->values()->all(),
            'proximoInscripcion' => $proximo === null ? null : $inscripciones->first(fn (Inscripcion $i): bool => $i->participante_id === $proximo->local_id || $i->participante_id === $proximo->visitante_id),
            'pareja' => $masProxima === null ? null : ['inscripcion' => $masProxima, 'companero' => $resumen->companero($masProxima, $yo)],
            'porValidar' => $resumen->porValidar($yo),
            'pendientes' => $resumen->pendientes($yo),
            'companeros' => $inscripciones->mapWithKeys(fn (Inscripcion $i): array => [$i->id => $resumen->companero($i, $yo)]),
        ])->title('Inicio');
    }
}
