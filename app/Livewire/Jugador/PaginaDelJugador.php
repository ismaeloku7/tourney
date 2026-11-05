<?php

namespace App\Livewire\Jugador;

use App\Competicion\Errores\ErrorDeCompeticion;
use App\Models\Jugador;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Lo común a las páginas del portal del jugador: quién es, el aviso de
 * lo último que ha hecho y cómo se ejecuta una acción del dominio
 * enseñando su error sin romper la página.
 */
abstract class PaginaDelJugador extends Component
{
    public ?string $aviso = null;

    protected function yo(): Jugador
    {
        $yo = Auth::guard('jugador')->user();
        abort_unless($yo instanceof Jugador, 403);

        return $yo;
    }

    protected function accion(callable $hacer, string $hecho): void
    {
        try {
            $hacer();
        } catch (ErrorDeCompeticion $e) {
            $this->aviso = null;
            $this->addError('portal', $e->getMessage());

            return;
        }

        $this->resetErrorBag();
        $this->aviso = $hecho;
    }
}
