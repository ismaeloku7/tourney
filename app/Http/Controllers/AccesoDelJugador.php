<?php

namespace App\Http\Controllers;

use App\Actions\Jugadores\AccesosDirectos;
use App\Actions\Jugadores\MandarEnlaceDeAcceso;
use App\Actions\Jugadores\UsarEnlaceDeAcceso;
use App\Models\Jugador;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Entrar con el enlace del correo o con el de acceso directo de la pareja, y salir del portal del jugador. */
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
        $destino = is_string($destino) && MandarEnlaceDeAcceso::destinoValido($destino) ? $destino : route('jugador.portal');

        return redirect()->to($destino);
    }

    /** El enlace de acceso directo de una pareja: no caduca, lleva a su torneo. */
    public function directo(Request $request, string $token, AccesosDirectos $accesos): RedirectResponse|View
    {
        $acceso = $accesos->usar($token);
        $jugador = $acceso?->jugador()->first();

        if ($acceso === null || $jugador === null) {
            return view('publico.enlace-caducado', ['mensaje' => 'La organización lo ha retirado o la pareja ya no está en el torneo. Pide un enlace con tu correo o pregunta a la organización.']);
        }

        self::iniciarSesion($request, $jugador);

        return redirect()->route('jugador.inscripcion', $acceso->inscripcion_id);
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
        // Sigue dentro de su portal 30 días (auth.guards.jugador.remember).
        Auth::guard('jugador')->login($jugador, remember: true);

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        $jugador->forceFill(['ultimo_acceso' => now()])->save();
    }
}
