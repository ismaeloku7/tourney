<?php

namespace App\Notifications;

use App\Filament\Competicion\Marcador;
use App\Models\Partido;

/** La organización pasa el partido a otra pista para adelantarlo. */
class CambioDePista extends AvisoAlJugador
{
    public function __construct(public Partido $partido)
    {
        parent::__construct();
    }

    public function titulo(): string
    {
        return 'Cambio de pista: '.($this->partido->pista->nombre ?? '').' hacia las '.($this->partido->franja?->inicio->format('H:i') ?? '');
    }

    public function texto(): string
    {
        return Marcador::lado($this->partido, true).' – '.Marcador::lado($this->partido, false).'. Estad pendientes del partido anterior en esa pista.';
    }

    public function url(): string
    {
        return '/jugador';
    }

    public function etiqueta(): string
    {
        return 'pista-'.$this->partido->id;
    }
}
