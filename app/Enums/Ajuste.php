<?php

namespace App\Enums;

use App\Enums\Concerns\ConEtiqueta;

/**
 * Las claves de la tabla `ajustes` de la base del organizador: su marca,
 * su zona horaria y sus textos legales. La identidad (slug, nombre,
 * estado, plan) vive en la central.
 */
enum Ajuste: string
{
    use ConEtiqueta;

    case NombrePublico = 'nombre_publico';
    case LogoPath = 'logo_path';
    case ColorPrimario = 'color_primario';
    case ColorSecundario = 'color_secundario';
    case ZonaHoraria = 'zona_horaria';
    case TextoLegalAviso = 'texto_legal_aviso';
    case TextoLegalPrivacidad = 'texto_legal_privacidad';
    case TextoLegalCondiciones = 'texto_legal_condiciones';
    /** Si en público se enseña el alias de los jugadores en lugar de su nombre. */
    case PrivacidadAliasPorDefecto = 'privacidad_alias_por_defecto';
    case ContactoEmail = 'contacto_email';
    case ContactoTelefono = 'contacto_telefono';
    /** Si cualquiera de la pareja edita los datos del otro desde su portal (si no, solo el capitán). */
    case ParejaEditaAlCompanero = 'pareja_edita_al_companero';
    /** Si el correo sale por el servidor SMTP del organizador (los de abajo) en vez del de la plataforma. */
    case CorreoPropio = 'correo_propio';
    case CorreoServidor = 'correo_servidor';
    case CorreoPuerto = 'correo_puerto';
    /** ssl (465), tls (STARTTLS, 587) o ninguno. */
    case CorreoCifrado = 'correo_cifrado';
    case CorreoUsuario = 'correo_usuario';
    /** Cifrada con la clave de la aplicación; nunca vuelve al formulario. */
    case CorreoClave = 'correo_clave';
    case CorreoRemitente = 'correo_remitente';
    case CorreoRemitenteNombre = 'correo_remitente_nombre';

    public function porDefecto(): bool|string|null
    {
        return match ($this) {
            self::ZonaHoraria => 'Europe/Madrid',
            self::ColorPrimario => '#4cc17d',
            self::PrivacidadAliasPorDefecto => false,
            self::CorreoPropio => false,
            self::ParejaEditaAlCompanero => false,
            self::CorreoCifrado => 'tls',
            default => null,
        };
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::NombrePublico => 'Nombre público',
            self::LogoPath => 'Logotipo',
            self::ColorPrimario => 'Color principal',
            self::ColorSecundario => 'Color secundario',
            self::ZonaHoraria => 'Zona horaria',
            self::TextoLegalAviso => 'Aviso legal',
            self::TextoLegalPrivacidad => 'Política de privacidad',
            self::TextoLegalCondiciones => 'Condiciones de inscripción',
            self::PrivacidadAliasPorDefecto => 'Mostrar alias en público',
            self::ContactoEmail => 'Correo de contacto',
            self::ContactoTelefono => 'Teléfono de contacto',
            self::ParejaEditaAlCompanero => 'Los dos jugadores editan los datos del otro',
            self::CorreoPropio => 'Servidor de correo propio',
            self::CorreoServidor => 'Servidor SMTP',
            self::CorreoPuerto => 'Puerto',
            self::CorreoCifrado => 'Cifrado',
            self::CorreoUsuario => 'Usuario',
            self::CorreoClave => 'Contraseña',
            self::CorreoRemitente => 'Correo del remitente',
            self::CorreoRemitenteNombre => 'Nombre del remitente',
        };
    }
}
