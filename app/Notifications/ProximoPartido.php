<?php

namespace App\Notifications;

use App\Filament\Competicion\Marcador;
use App\Models\Partido;

/** El partido empieza en media hora. Solo push. */
class ProximoPartido extends AvisoAlJugador
{
    public function __construct(public Partido $partido)
    {
        parent::__construct();
    }

    public function titulo(): string
    {
        return 'Juegas a las '.($this->partido->franja?->inicio->format('H:i') ?? '').($this->partido->pista !== null ? ' en '.$this->partido->pista->nombre : '');
    }

    public function texto(): string
    {
        return Marcador::lado($this->partido, true).' – '.Marcador::lado($this->partido, false).'.';
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
        return 'proximo-'.$this->partido->id;
    }
}
