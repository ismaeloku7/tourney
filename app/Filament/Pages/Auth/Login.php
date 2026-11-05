<?php

namespace App\Filament\Pages\Auth;

use App\Actions\Equipo\IniciarSesion;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as LoginDeFilament;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;

/**
 * La entrada del equipo, con las reglas de `IniciarSesion`: organizador
 * del host, usuario activo, cinco intentos y quince minutos. Con
 * «mantener la sesión iniciada» (marcado por defecto), 30 días dentro.
 */
class Login extends LoginDeFilament
{
    public function authenticate(): ?LoginResponse
    {
        $datos = $this->form->getState();

        try {
            app(IniciarSesion::class)->handle((string) $datos['email'], (string) $datos['password'], (bool) ($datos['remember'] ?? false));
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(['data.email' => $e->errors()['email'] ?? []]);
        }

        return app(LoginResponse::class);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            $this->getEmailFormComponent(),
            $this->getPasswordFormComponent(),
            $this->getRememberFormComponent()->label('Mantener la sesión iniciada (30 días)')->default(true),
        ]);
    }
}
