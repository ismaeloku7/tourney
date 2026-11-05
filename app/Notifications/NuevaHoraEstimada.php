<?php

namespace App\Notifications;

use App\Filament\Competicion\Marcador;
use App\Models\Partido;

/** La hora estimada del partido ha cambiado bastante. Solo push. */
class NuevaHoraEstimada extends AvisoAlJugador
{
    public function __construct(public Partido $partido)
    {
        parent::__construct();
    }

    public function titulo(): string
    {
        return 'Ahora hacia las '.($this->partido->franja?->inicio->format('H:i') ?? '').($this->partido->pista !== null ? ' en '.$this->partido->pista->nombre : '');
    }

    public function texto(): string
    {
        return Marcador::lado($this->partido, true).' – '.Marcador::lado($this->partido, false).'. Es una hora estimada: estad pendientes del partido anterior en la pista.';
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
        return 'hora-'.$this->partido->id;
    }
}
