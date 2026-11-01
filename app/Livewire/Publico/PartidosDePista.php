<?php

namespace App\Livewire\Publico;

use App\Models\Partido;
use App\Models\Pista;
use App\Models\Torneo;
use App\Publico\DatosDelTorneo;
use App\Publico\TorneosPublicos;
use Carbon\CarbonImmutable;
use chillerlan\QRCode\QRCode;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

/**
 * Los partidos de una pista, para colgar como QR en la propia pista: los de
 * hoy (o los del primer día del torneo si aún no ha empezado), refrescados
 * cada 15 segundos.
 */
#[Layout('components.layouts.publico')]
class PartidosDePista extends Component
{
    public Torneo $torneo;

    public Pista $pista;

    public function mount(Torneo $torneo, Pista $pista): void
    {
        abort_unless(TorneosPublicos::esPublico($torneo), 404);
        abort_unless($torneo->torneoPistas()->where('pista_id', $pista->id)->exists(), 404);

        $this->torneo = $torneo;
        $this->pista = $pista;
    }

    public function render(): View
    {
        $datos = new DatosDelTorneo($this->torneo->refresh());
        $dia = now()->between($this->torneo->fecha_inicio->startOfDay(), $this->torneo->fecha_fin->endOfDay()) ? now()->toDateString() : $this->torneo->fecha_inicio->toDateString();

        $partidos = $this->torneo->horarios_publicados_en === null ? collect() : $datos->partidosConHora()
            ->where('partidos.pista_id', $this->pista->id)
            ->whereDate('franjas.inicio', $dia)
            ->get()
            ->map(fn (Partido $p): array => $datos->partido($p) + ['horaCorta' => $p->franja->inicio->format('H:i'), 'categoria' => $p->fase->categoria->nombre, 'estado' => $p->estado]);

        return view('livewire.publico.partidos-de-pista', [
            'dia' => CarbonImmutable::parse($dia)->translatedFormat('l j \d\e F'),
            'partidos' => $partidos->all(),
            'qr' => $this->qr(),
        ]);
    }

    /** El QR de esta misma página, como imagen SVG en data URI; nulo si no se puede. */
    private function qr(): ?string
    {
        try {
            $qr = (new QRCode)->render(request()->url());

            return is_string($qr) ? $qr : null;
        } catch (Throwable) {
            return null;
        }
    }
}
