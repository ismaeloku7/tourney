<?php

namespace App\Livewire\Jugador;

use App\Jugadores\PartidosDelJugador;
use App\Models\Partido;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;

/** Todos los partidos del jugador, de todos sus torneos: los que quedan por jugar primero. */
#[Layout('components.layouts.jugador')]
class MisPartidos extends PaginaDelJugador
{
    use ConResultados;

    public function render(PartidosDelJugador $partidos): View
    {
        $yo = $this->yo();
        $filas = $partidos->todos($yo)->map(fn (Partido $p): array => $partidos->fila($p, $yo));

        return view('livewire.jugador.mis-partidos', [
            'porJugar' => $filas->where('porJugar', true)->values()->all(),
            'jugados' => $filas->where('porJugar', false)->reverse()->values()->all(),
        ])->title('Partidos');
    }
}
