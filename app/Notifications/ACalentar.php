<?php

namespace App\Notifications;

use App\Filament\Competicion\Marcador;
use App\Models\Partido;

/** Pista libre: a calentar. Solo push (y el historial del portal). */
class ACalentar extends AvisoAlJugador
{
    public function __construct(public Partido $partido)
    {
        parent::__construct();
    }

    public function titulo(): string
    {
        return 'Pista libre: a calentar'.($this->partido->pista !== null ? ' · '.$this->partido->pista->nombre : '');
    }

    public function texto(): string
    {
        return Marcador::lado($this->partido, true).' – '.Marcador::lado($this->partido, false).'. Pasad ya a la pista: el partido anterior ha terminado. Tenéis el calentamiento y empezáis.';
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
        return 'calentar-'.$this->partido->id;
    }
}
