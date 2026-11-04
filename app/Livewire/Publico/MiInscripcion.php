<?php

namespace App\Livewire\Publico;

use App\Actions\Jugadores\CambiarNivel;
use App\Competicion\Errores\ErrorDeCompeticion;
use App\Enums\EstadoInscripcion;
use App\Enums\EstadoTorneo;
use App\Models\Inscripcion;
use App\Models\Jugador;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * La inscripción del jugador en un torneo, desde su portal: el torneo, la
 * categoría y la pareja, y el nivel de los dos jugadores, que cualquiera
 * de ellos puede poner mientras la pareja siga en un torneo sin terminar.
 */
#[Layout('components.layouts.publico')]
class MiInscripcion extends Component
{
    public Inscripcion $inscripcion;

    /** @var array<int, string> jugador => nivel */
    public array $niveles = [];

    public ?string $aviso = null;

    public function mount(Inscripcion $inscripcion): void
    {
        $inscripcion->load(['categoria.torneo', 'participante.jugadores']);
        abort_unless($inscripcion->participante->jugadores->contains(fn (Jugador $j): bool => $j->is($this->yo())), 404);

        $this->inscripcion = $inscripcion;
        foreach ($inscripcion->participante->jugadores as $jugador) {
            $this->niveles[$jugador->id] = (string) $jugador->nivel_declarado;
        }
    }

    public function guardarNiveles(CambiarNivel $cambiar): void
    {
        abort_unless($this->editable(), 403);

        $this->aviso = null;
        $this->resetErrorBag();
        $hayErrores = false;

        foreach ($this->inscripcion->participante->jugadores()->get() as $jugador) {
            $nuevo = trim($this->niveles[$jugador->id] ?? '');
            if ($nuevo === (string) $jugador->nivel_declarado) {
                continue;
            }

            try {
                $cambiar->handle($this->yo(), $jugador, $nuevo);
            } catch (ErrorDeCompeticion $e) {
                $this->addError('niveles.'.$jugador->id, $e->getMessage());
                $hayErrores = true;
            }
        }

        if (! $hayErrores) {
            $this->aviso = 'Niveles guardados.';
        }
    }

    public function editable(): bool
    {
        return $this->inscripcion->estado !== EstadoInscripcion::Baja
            && ! in_array($this->inscripcion->categoria->torneo->estado, [EstadoTorneo::Finalizado, EstadoTorneo::Archivado], true);
    }

    public function render(): View
    {
        $participante = $this->inscripcion->participante->load('jugadores');

        return view('livewire.publico.mi-inscripcion', [
            'yo' => $this->yo(),
            'torneo' => $this->inscripcion->categoria->torneo,
            'jugadores' => $participante->jugadores,
            'media' => $participante->nivelMedioTexto(),
            'editable' => $this->editable(),
        ]);
    }

    private function yo(): Jugador
    {
        $yo = Auth::guard('jugador')->user();
        abort_unless($yo instanceof Jugador, 403);

        return $yo;
    }
}
