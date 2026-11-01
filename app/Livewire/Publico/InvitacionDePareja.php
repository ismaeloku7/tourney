<?php

namespace App\Livewire\Publico;

use App\Actions\Jugadores\ConfirmarInvitacion;
use App\Http\Controllers\AccesoDelJugador;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * El enlace de la invitación: quién te inscribe, dónde y un botón para
 * confirmar. Al confirmar, la pareja cuenta y entras en tu portal.
 */
#[Layout('components.layouts.publico')]
class InvitacionDePareja extends Component
{
    #[Locked]
    public string $token = '';

    public bool $privacidad = false;

    public ?string $confirmada = null;

    public function mount(string $token): void
    {
        $this->token = $token;
    }

    public function confirmar(ConfirmarInvitacion $confirmar): void
    {
        $invitacion = ConfirmarInvitacion::buscar($this->token);
        if ($invitacion === null) {
            return;
        }

        if ($invitacion->jugador->consentimiento_en === null) {
            $this->validate(['privacidad' => ['accepted']], ['privacidad.accepted' => 'Hay que aceptar la política de privacidad.']);
        }

        $inscripcion = $confirmar->handle($invitacion);
        AccesoDelJugador::iniciarSesion(request(), $invitacion->jugador);

        $this->confirmada = $inscripcion->estado->etiqueta();
    }

    public function render(): View
    {
        $invitacion = $this->confirmada === null ? ConfirmarInvitacion::buscar($this->token) : null;

        return view('livewire.publico.invitacion-de-pareja', [
            'invitacion' => $invitacion,
            'quienInvita' => $invitacion !== null ? $invitacion->participante->participanteJugadores()->where('jugador_id', '!=', $invitacion->jugador_id)->with('jugador')->first()?->jugador : null,
        ]);
    }
}
