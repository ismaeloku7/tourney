<?php

namespace App\Http\Controllers;

use App\Actions\Jugadores\MandarEnlaceDeAcceso;
use App\Actions\Jugadores\UsarEnlaceDeAcceso;
use App\Models\Jugador;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Entrar con el enlace del correo y salir del portal del jugador. */
class AccesoDelJugador
{
    public function entrar(Request $request, string $token, UsarEnlaceDeAcceso $usar): RedirectResponse|View
    {
        $jugador = $usar->handle($token);

        if ($jugador === null) {
            return view('publico.enlace-caducado');
        }

        self::iniciarSesion($request, $jugador);

        $destino = $request->query('destino');

        return redirect(MandarEnlaceDeAcceso::destinoValido(is_string($destino) ? $destino : null) ? (string) $destino : route('jugador.portal'));
    }

    public function salir(Request $request): RedirectResponse
    {
        Auth::guard('jugador')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    public static function iniciarSesion(Request $request, Jugador $jugador): void
    {
        Auth::guard('jugador')->login($jugador);

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        $jugador->forceFill(['ultimo_acceso' => now()])->save();
    }
}
