<?php

namespace App\Notifications;

use App\Filament\Competicion\Marcador;
use App\Models\Partido;

/** El rival ha anotado el resultado: hay que validarlo en el portal. Sin correo. */
class ResultadoPorValidar extends AvisoAlJugador
{
    public function __construct(public Partido $partido)
    {
        parent::__construct();
    }

    public function titulo(): string
    {
        return 'Valida el resultado';
    }

    public function texto(): string
    {
        return Marcador::lado($this->partido, true).' – '.Marcador::lado($this->partido, false).': '.Marcador::resultado($this->partido).'. Si no lo validas, contará solo.';
    }

    public function url(): string
    {
        return '/jugador';
    }

    protected function conCorreo(): bool
    {
        return false;
    }

    public function etiqueta(): string
    {
        return 'validar-'.$this->partido->id;
    }
}
