<?php

namespace App\Notifications;

use App\Filament\Competicion\Marcador;
use App\Models\Partido;

/** El resultado de un partido ya cuenta. Sin correo. */
class ResultadoDelPartido extends AvisoAlJugador
{
    public function __construct(public Partido $partido)
    {
        parent::__construct();
    }

    public function titulo(): string
    {
        return 'Resultado: '.Marcador::resultado($this->partido);
    }

    public function texto(): string
    {
        return Marcador::lado($this->partido, true).' – '.Marcador::lado($this->partido, false).' · '.$this->partido->fase->categoria->nombre.'.';
    }

    public function url(): string
    {
        return '/torneos/'.$this->partido->fase->categoria->torneo->slug.'?ver='.($this->partido->grupo_id !== null ? 'grupos' : 'cuadro');
    }

    protected function conCorreo(): bool
    {
        return false;
    }

    public function etiqueta(): string
    {
        return 'resultado-'.$this->partido->id;
    }
}
