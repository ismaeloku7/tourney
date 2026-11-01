<?php

namespace App\Livewire\Publico;

use App\Enums\EstadoTorneo;
use App\Models\Torneo;
use App\Publico\DatosDelTorneo;
use App\Publico\TorneosPublicos;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * La ficha pública del torneo: datos y categorías con plazas, y las
 * pestañas de inscritos, grupos, cuadro y horarios. Mientras el torneo
 * está en juego se refresca sola cada 15 segundos.
 */
#[Layout('components.layouts.publico')]
class FichaDelTorneo extends Component
{
    public const PESTANAS = ['info' => 'Torneo', 'inscritos' => 'Inscritos', 'grupos' => 'Grupos', 'cuadro' => 'Cuadro', 'horarios' => 'Horarios'];

    public Torneo $torneo;

    #[Url(as: 'ver')]
    public string $pestana = 'info';

    public function mount(Torneo $torneo): void
    {
        abort_unless(TorneosPublicos::esPublico($torneo), 404);

        $this->torneo = $torneo;

        if (! array_key_exists($this->pestana, self::PESTANAS)) {
            $this->pestana = 'info';
        }
    }

    public function ver(string $pestana): void
    {
        if (array_key_exists($pestana, self::PESTANAS)) {
            $this->pestana = $pestana;
        }
    }

    public function render(): View
    {
        $datos = new DatosDelTorneo($this->torneo->refresh()->load(['categorias', 'sede']));

        return view('livewire.publico.ficha-del-torneo', [
            'datos' => $datos,
            'enJuego' => $this->torneo->estado === EstadoTorneo::EnJuego,
            'contenido' => match ($this->pestana) {
                'inscritos' => $datos->inscritos(),
                'grupos' => $datos->grupos(),
                'cuadro' => $datos->cuadros(),
                'horarios' => $datos->horarios(),
                default => null,
            },
        ])->title($this->torneo->nombre);
    }
}
