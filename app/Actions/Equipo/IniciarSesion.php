<?php

namespace App\Actions\Equipo;

use App\Models\User;
use App\Tenancy\OrganizadorActual;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Entrada del equipo al panel.
 *
 * - Solo encuentra usuarios del organizador del host (el modelo vive en su base).
 * - Un usuario inactivo no entra.
 * - Cinco fallos por organizador, correo e IP bloquean quince minutos.
 * - No hay «recordarme»: la sesión caduca a los 120 minutos sin uso.
 */
class IniciarSesion
{
    public const MAX_INTENTOS = 5;

    public const BLOQUEO_MINUTOS = 15;

    private StatefulGuard $guard;

    public function __construct(
        AuthFactory $auth,
        private OrganizadorActual $organizadorActual,
        private Request $request,
    ) {
        $guard = $auth->guard('web');

        if (! $guard instanceof StatefulGuard) {
            throw new LogicException('El guard web del equipo tiene que ser de sesión.');
        }

        $this->guard = $guard;
    }

    /**
     * @throws ValidationException
     */
    public function handle(string $email, #[\SensitiveParameter] string $clave): User
    {
        $llave = $this->llave($email);

        if (RateLimiter::tooManyAttempts($llave, self::MAX_INTENTOS)) {
            event(new Lockout($this->request));

            $segundos = RateLimiter::availableIn($llave);

            throw ValidationException::withMessages([
                'email' => __('auth.throttle', ['seconds' => $segundos, 'minutes' => (int) ceil($segundos / 60)]),
            ]);
        }

        $credenciales = ['email' => Str::lower(trim($email)), 'password' => $clave, 'activo' => true];

        if (! $this->guard->attempt($credenciales)) {
            RateLimiter::hit($llave, self::BLOQUEO_MINUTOS * 60);

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        RateLimiter::clear($llave);

        if ($this->request->hasSession()) {
            $this->request->session()->regenerate();
        }

        $usuario = $this->guard->user();

        if (! $usuario instanceof User) {
            throw new LogicException('El guard web tiene que autenticar App\Models\User.');
        }

        $usuario->forceFill(['ultimo_acceso' => now()])->save();

        return $usuario;
    }

    private function llave(string $email): string
    {
        return 'login-equipo:'.$this->organizadorActual->slug().'|'.Str::lower(trim($email)).'|'.$this->request->ip();
    }
}
