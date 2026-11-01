<?php

namespace App\Actions\Plataforma;

use App\Enums\EstadoOrganizador;
use App\Mail\VerificarCorreoDelOrganizador;
use App\Models\Organizador;
use App\Models\Plan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * El alta self-service: crea el organizador pendiente de verificar, con su
 * base y su propietario, y le manda el correo con el enlace de
 * verificación (48 horas). Hasta que verifique, sus hosts no responden.
 */
final class SolicitarAltaDeOrganizador
{
    public const HORAS_PARA_VERIFICAR = 48;

    public function __construct(private readonly CrearOrganizador $crear) {}

    /**
     * @throws ValidationException
     */
    public function handle(string $slug, string $nombre, string $responsableNombre, string $responsableEmail, #[\SensitiveParameter] string $clave): Organizador
    {
        $organizador = $this->crear->handle(new DatosDeOrganizadorNuevo(
            slug: $slug,
            nombre: $nombre,
            responsableNombre: $responsableNombre,
            responsableEmail: $responsableEmail,
            responsablePassword: $clave,
            plan: Plan::GRATIS,
            estado: EstadoOrganizador::Pendiente,
        ));

        $token = Str::random(64);
        $organizador->forceFill([
            'verificacion_token' => hash('sha256', $token),
            'verificacion_expira_en' => now()->addHours(self::HORAS_PARA_VERIFICAR),
            'verificado_en' => null,
        ])->save();

        Mail::to($organizador->responsable_email)->send(new VerificarCorreoDelOrganizador($organizador, $token));

        return $organizador;
    }
}
