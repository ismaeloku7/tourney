<?php

namespace App\Livewire\Publico;

use App\Actions\Jugadores\MandarEnlaceDeAcceso;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * La puerta del portal del jugador: escribe su correo y recibe un enlace.
 * La respuesta es la misma tanto si el correo es de un jugador como si no.
 */
#[Layout('components.layouts.publico')]
class AccesoJugador extends Component
{
    private const ENVIOS = 5;

    public string $email = '';

    #[Url]
    public ?string $destino = null;

    public bool $enviado = false;

    public function mount(): void
    {
        if (Auth::guard('jugador')->check()) {
            $this->redirect(MandarEnlaceDeAcceso::destinoValido($this->destino) ? (string) $this->destino : route('jugador.portal'));
        }
    }

    public function enviar(MandarEnlaceDeAcceso $mandar): void
    {
        $this->validate(['email' => ['required', 'email', 'max:190']], [], ['email' => 'correo electrónico']);

        $llave = 'enlace-jugador:'.request()->ip().'|'.Str::lower(trim($this->email));
        if (RateLimiter::tooManyAttempts($llave, self::ENVIOS)) {
            $this->addError('email', 'Has pedido demasiados enlaces. Espera unos minutos.');

            return;
        }
        RateLimiter::hit($llave, 15 * 60);

        $mandar->handle($this->email, $this->destino);
        $this->enviado = true;
    }

    public function render(): View
    {
        return view('livewire.publico.acceso-jugador');
    }
}
