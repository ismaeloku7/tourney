<?php

namespace App\Notifications;

use App\Filament\Competicion\Marcador;
use App\Models\Partido;

/** Sois los siguientes. Solo push (y el historial del portal). */
class SoisLosSiguientes extends AvisoAlJugador
{
    public function __construct(public Partido $partido)
    {
        parent::__construct();
    }

    public function titulo(): string
    {
        return 'Sois los siguientes'.($this->partido->pista !== null ? ' · '.$this->partido->pista->nombre : '');
    }

    public function texto(): string
    {
        return Marcador::lado($this->partido, true).' – '.Marcador::lado($this->partido, false).'. Estad atentos: en cuanto acabe el partido que se está jugando en la pista, os toca.';
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
        return 'siguientes-'.$this->partido->id;
    }
}
