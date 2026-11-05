<?php

namespace App\Livewire\Jugador;

use App\Jugadores\PartidosDelJugador;
use App\Jugadores\ResumenDelJugador;
use App\Models\Inscripcion;
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
        $proximo = $resumen->proximoPartido($yo);
        $masProxima = $resumen->inscripcionMasProxima($yo);

        return view('livewire.jugador.inicio', [
            'yo' => $yo,
            'inscripciones' => $inscripciones,
            'activas' => $resumen->activas($yo)->count(),
            'proximo' => $proximo === null ? null : $partidos->fila($proximo, $yo),
            'proximoInscripcion' => $proximo === null ? null : $inscripciones->first(fn (Inscripcion $i): bool => $i->participante_id === $proximo->local_id || $i->participante_id === $proximo->visitante_id),
            'pareja' => $masProxima === null ? null : ['inscripcion' => $masProxima, 'companero' => $resumen->companero($masProxima, $yo)],
            'porValidar' => $resumen->porValidar($yo),
            'pendientes' => $resumen->pendientes($yo),
            'companeros' => $inscripciones->mapWithKeys(fn (Inscripcion $i): array => [$i->id => $resumen->companero($i, $yo)]),
        ])->title('Inicio');
    }
}
