<?php

namespace App\Notifications;

use App\Filament\Competicion\Marcador;
use App\Models\Partido;

/** La pareja pasa al cuadro (o a la consolación) y ya tiene su primer cruce. */
class CruceAsignado extends AvisoAlJugador
{
    public function __construct(public Partido $partido)
    {
        parent::__construct();
    }

    public function titulo(): string
    {
        return 'Ya tenéis cruce en '.$this->partido->fase->nombre;
    }

    public function texto(): string
    {
        return Marcador::lado($this->partido, true).' – '.Marcador::lado($this->partido, false).' · '.$this->partido->fase->categoria->nombre.'.';
    }

    public function url(): string
    {
        return '/torneos/'.$this->partido->fase->categoria->torneo->slug.'?ver=cuadro';
    }

    public function lineas(): array
    {
        return ['La hora y la pista os llegarán con los horarios.'];
    }
}
