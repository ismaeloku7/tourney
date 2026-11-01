<?php

namespace App\Notifications;

use App\Models\Inscripcion;

/** La pareja está dentro: por fin, o al liberarse una plaza de la lista de espera. */
class InscripcionConfirmada extends AvisoAlJugador
{
    public function __construct(public Inscripcion $inscripcion, public bool $desdeEspera = false)
    {
        parent::__construct();
    }

    public function titulo(): string
    {
        return $this->desdeEspera ? '¡Tenéis plaza!' : 'Inscripción confirmada';
    }

    public function texto(): string
    {
        $categoria = $this->inscripcion->categoria;

        return ($this->desdeEspera ? 'Se ha liberado una plaza: ' : '').$this->inscripcion->participante->nombre.' estáis inscritos en '.$categoria->nombre.' de '.$categoria->torneo->nombre.'.';
    }

    public function url(): string
    {
        return '/torneos/'.$this->inscripcion->categoria->torneo->slug;
    }

    public function lineas(): array
    {
        return ['Los horarios os llegarán cuando se publiquen.'];
    }
}
