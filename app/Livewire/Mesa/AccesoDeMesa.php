<?php

namespace App\Livewire\Mesa;

use App\Actions\Equipo\IniciarSesion;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * La entrada a la mesa, para árbitros y para el resto del equipo, con las
 * reglas de `IniciarSesion` (activo, cinco intentos, quince minutos).
 */
#[Layout('components.layouts.publico')]
class AccesoDeMesa extends Component
{
    public string $email = '';

    public string $clave = '';

    public function mount(): void
    {
        if (Auth::guard('web')->user() instanceof User) {
            $this->redirect(route('mesa'));
        }
    }

    public function entrar(IniciarSesion $iniciar): void
    {
        $this->validate(['email' => ['required', 'email'], 'clave' => ['required', 'string']], [], ['email' => 'correo electrónico', 'clave' => 'contraseña']);

        try {
            $iniciar->handle($this->email, $this->clave);
        } catch (ValidationException $e) {
            $this->addError('email', (string) ($e->errors()['email'][0] ?? 'No se ha podido entrar.'));

            return;
        }

        $this->redirect(route('mesa'));
    }

    public function render(): View
    {
        return view('livewire.mesa.acceso-de-mesa');
    }
}
