<?php

namespace App\Actions\Jugadores;

use App\Mail\EnlaceDeAcceso;
use App\Models\EnlaceMagico;
use App\Models\Jugador;
use App\Tenancy\OrganizadorActual;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Manda al jugador un enlace para entrar sin contraseña: un solo uso y
 * quince minutos. Si el correo no es de ningún jugador no hace nada, y
 * quien lo pide no lo sabe: así no se averigua quién juega.
 */
final class MandarEnlaceDeAcceso
{
    public const MINUTOS = 15;

    public function handle(string $email, ?string $destino = null): void
    {
        $jugador = Jugador::query()->where('email', Str::lower(trim($email)))->whereNull('anonimizado_en')->first();

        if ($jugador === null) {
            return;
        }

        $token = Str::random(64);

        EnlaceMagico::query()->create([
            'jugador_id' => $jugador->id,
            'token' => hash('sha256', $token),
            'expira_en' => now()->addMinutes(self::MINUTOS),
        ]);

        $enlace = app(OrganizadorActual::class)->organizador()->url('jugador/entrar/'.$token);
        if (self::destinoValido($destino)) {
            $enlace .= '?destino='.urlencode((string) $destino);
        }

        Mail::to((string) $jugador->getAttribute('email'))->send(new EnlaceDeAcceso($jugador, $enlace));
    }

    /** Solo rutas de la propia web: «/torneos/x», nunca «//otro.sitio» ni una URL completa. */
    public static function destinoValido(?string $destino): bool
    {
        return is_string($destino) && str_starts_with($destino, '/') && ! str_starts_with($destino, '//') && ! str_contains($destino, '\\');
    }
}
