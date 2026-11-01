<?php

namespace App\Livewire\Publico;

use App\Enums\EstadoPartido;
use App\Models\Partido;
use App\Models\Torneo;
use App\Publico\DatosDelTorneo;
use App\Publico\TorneosPublicos;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * El tablero para la tele del club: lo que se juega ahora, lo que viene y
 * los últimos resultados, a pantalla completa y refrescado cada 5 segundos.
 */
#[Layout('components.layouts.tablero')]
class Tablero extends Component
{
    public Torneo $torneo;

    public function mount(Torneo $torneo): void
    {
        abort_unless(TorneosPublicos::esPublico($torneo), 404);

        $this->torneo = $torneo;
    }

    public function render(): View
    {
        $datos = new DatosDelTorneo($this->torneo->refresh());
        $ahora = now();

        $conHora = $datos->partidosConHora()->whereIn('partidos.estado', [EstadoPartido::Programado->value, EstadoPartido::EnJuego->value])->get();

        $enPista = $conHora->filter(fn (Partido $p): bool => $p->estado === EstadoPartido::EnJuego || ($p->franja->inicio->lte($ahora) && $p->franja->fin->gt($ahora)))->values();
        $proximos = $conHora->filter(fn (Partido $p): bool => $p->franja->inicio->gt($ahora))->take(8)->values();
        $ultimos = $datos->partidosConHora()->whereIn('partidos.estado', [EstadoPartido::Jugado->value, EstadoPartido::Walkover->value])
            ->reorder()->orderByDesc('partidos.updated_at')->limit(8)->get();

        return view('livewire.publico.tablero', [
            'datos' => $datos,
            'enPista' => $enPista->map(fn (Partido $p): array => $datos->partido($p) + ['pista' => $p->pista?->nombre, 'categoria' => $p->fase->categoria->nombre])->all(),
            'proximos' => $proximos->map(fn (Partido $p): array => $datos->partido($p) + ['pista' => $p->pista?->nombre, 'categoria' => $p->fase->categoria->nombre, 'horaCorta' => $p->franja->inicio->format('H:i')])->all(),
            'ultimos' => $ultimos->map(fn (Partido $p): array => $datos->partido($p) + ['categoria' => $p->fase->categoria->nombre])->all(),
        ]);
    }
}
