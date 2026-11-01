<?php

namespace App\Notifications;

use App\Models\Torneo;

/** Los horarios del torneo ya están: con los partidos de este jugador. */
class HorariosPublicados extends AvisoAlJugador
{
    /** @param  list<string>  $partidos  «sábado 10, 10:00 · Central · García / López – Pérez / Ruiz» */
    public function __construct(public Torneo $torneo, public array $partidos)
    {
        parent::__construct();
    }

    public function titulo(): string
    {
        return 'Horarios de '.$this->torneo->nombre;
    }

    public function texto(): string
    {
        return $this->partidos === []
            ? 'Ya están publicados los horarios.'
            : 'Tu primer partido: '.$this->partidos[0].'.';
    }

    public function url(): string
    {
        return '/torneos/'.$this->torneo->slug.'?ver=horarios';
    }

    public function lineas(): array
    {
        return $this->partidos === [] ? [] : ['Tus partidos:', ...array_map(fn (string $p): string => '· '.$p, $this->partidos)];
    }

    public function etiqueta(): string
    {
        return 'horarios-'.$this->torneo->id;
    }
}
