<?php

namespace App\Notifications;

use App\Models\Comunicacion;

/** Un aviso libre de la organización, por los canales que eligió. */
class AvisoDeLaOrganizacion extends AvisoAlJugador
{
    public function __construct(public Comunicacion $comunicacion)
    {
        parent::__construct();
    }

    public function titulo(): string
    {
        return $this->comunicacion->titulo;
    }

    public function texto(): string
    {
        return $this->comunicacion->cuerpo;
    }

    public function url(): string
    {
        return '/torneos/'.$this->comunicacion->torneo->slug;
    }

    protected function conCorreo(): bool
    {
        return in_array('correo', $this->comunicacion->canales, true);
    }

    protected function conPush(): bool
    {
        return in_array('push', $this->comunicacion->canales, true);
    }
}
