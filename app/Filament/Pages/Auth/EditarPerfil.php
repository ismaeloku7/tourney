<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\EditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Support\Facades\Password;

/**
 * «Mi cuenta»: cada uno cambia su nombre, su teléfono y su contraseña. El
 * correo y el rol los lleva el propietario.
 */
class EditarPerfil extends EditProfile
{
    protected static ?string $slug = 'mi-cuenta';

    public static function getLabel(): string
    {
        return 'Mi cuenta';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nombre')
                ->label('Nombre')
                ->required()
                ->maxLength(120),
            TextInput::make('apellidos')
                ->label('Apellidos')
                ->maxLength(160),
            TextInput::make('email')
                ->label('Correo electrónico')
                ->disabled()
                ->dehydrated(false),
            TextInput::make('telefono')
                ->label('Teléfono')
                ->tel()
                ->maxLength(30),
            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
            $this->getCurrentPasswordFormComponent(),
        ]);
    }

    /** La actual solo se pide al cambiar la contraseña. */
    protected function getCurrentPasswordFormComponent(): Component
    {
        return parent::getCurrentPasswordFormComponent()->visible(fn (Get $get): bool => filled($get('password')));
    }

    /** Sesión nueva tras cambiar la contraseña: si alguien había llegado a esta sesión, el cambio lo echa. */
    protected function afterSave(): void
    {
        if (filled($this->data['password'] ?? null)) {
            $usuario = $this->getUser();
            if ($usuario instanceof CanResetPassword) {
                Password::broker('users')->deleteToken($usuario);
            }

            if (request()->hasSession()) {
                request()->session()->regenerate();
            }
        }
    }
}
