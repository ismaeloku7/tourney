<?php

namespace App\Tenancy;

use App\Enums\Ajuste;
use App\Models\ValorDeAjuste;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;
use Throwable;

/**
 * El correo sale por el servidor del organizador si lo ha configurado en
 * sus ajustes (SMTP propio, con su remitente); si no, por el de la
 * plataforma. Al salir del organizador se deja todo como estaba, para
 * que una cola que atiende a varios no mande con el servidor de otro.
 */
final class CorreoDelOrganizador implements TenancyBootstrapper
{
    /** El nombre del mailer del organizador en config('mail.mailers'). */
    public const MAILER = 'organizador';

    /** @var array{default: mixed, from: mixed}|null */
    private ?array $original = null;

    public function bootstrap(Tenant $tenant): void
    {
        $servidor = self::servidor();

        if ($servidor === null) {
            return;
        }

        $this->original = ['default' => config('mail.default'), 'from' => config('mail.from')];

        config([
            'mail.mailers.'.self::MAILER => $servidor['mailer'],
            'mail.default' => self::MAILER,
            'mail.from' => $servidor['from'],
        ]);

        Mail::forgetMailers();
    }

    public function revert(): void
    {
        if ($this->original === null) {
            return;
        }

        config([
            'mail.default' => $this->original['default'],
            'mail.from' => $this->original['from'],
            'mail.mailers.'.self::MAILER => null,
        ]);
        $this->original = null;

        Mail::forgetMailers();
    }

    /**
     * El servidor del organizador activo, o null si no tiene uno propio (o
     * le falta el servidor o el remitente).
     *
     * @return array{mailer: array<string, mixed>, from: array{address: string, name: string}}|null
     */
    public static function servidor(): ?array
    {
        try {
            if (ValorDeAjuste::obtener(Ajuste::CorreoPropio) !== true) {
                return null;
            }

            $host = (string) ValorDeAjuste::obtener(Ajuste::CorreoServidor);
            $remitente = (string) ValorDeAjuste::obtener(Ajuste::CorreoRemitente);

            if ($host === '' || $remitente === '') {
                return null;
            }

            $cifrado = (string) ValorDeAjuste::obtener(Ajuste::CorreoCifrado);
            $nombre = (string) (ValorDeAjuste::obtener(Ajuste::CorreoRemitenteNombre) ?: ValorDeAjuste::obtener(Ajuste::NombrePublico) ?: config('app.name'));

            return [
                'mailer' => [
                    'transport' => 'smtp',
                    // smtps: SSL desde el principio (465). smtp: en claro y STARTTLS si el servidor lo ofrece (587, 25).
                    'scheme' => $cifrado === 'ssl' ? 'smtps' : 'smtp',
                    'host' => $host,
                    'port' => (int) (ValorDeAjuste::obtener(Ajuste::CorreoPuerto) ?: ($cifrado === 'ssl' ? 465 : 587)),
                    'username' => ValorDeAjuste::obtener(Ajuste::CorreoUsuario) ?: null,
                    'password' => self::clave(),
                    'timeout' => 20,
                ],
                'from' => ['address' => $remitente, 'name' => $nombre],
            ];
        } catch (Throwable) {
            // Sin la tabla de ajustes todavía (un organizador recién creado) o
            // con la base caída: el de la plataforma.
            return null;
        }
    }

    /** La contraseña del servidor, guardada cifrada con la clave de la aplicación. */
    private static function clave(): ?string
    {
        $cifrada = ValorDeAjuste::obtener(Ajuste::CorreoClave);

        if (! is_string($cifrada) || $cifrada === '') {
            return null;
        }

        try {
            return Crypt::decryptString($cifrada);
        } catch (DecryptException) {
            return null;
        }
    }
}
