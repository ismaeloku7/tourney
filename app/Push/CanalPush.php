<?php

namespace App\Push;

use App\Models\Jugador;
use App\Notifications\AvisoAlJugador;
use Illuminate\Notifications\Notification;

/** El canal «push» de las notificaciones de Laravel: a los dispositivos del jugador. */
class CanalPush
{
    public function __construct(private Push $push) {}

    public function send(object $notifiable, Notification $notificacion): void
    {
        if (! $notifiable instanceof Jugador || ! $notificacion instanceof AvisoAlJugador) {
            return;
        }

        $this->push->aJugadores([$notifiable], $notificacion->titulo(), $notificacion->texto(), $notificacion->url(), $notificacion->etiqueta());
    }
}
