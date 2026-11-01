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

    public function porDefecto(): bool|string|null
    {
        return match ($this) {
            self::ZonaHoraria => 'Europe/Madrid',
            self::ColorPrimario => '#1d4ed8',
            self::PrivacidadAliasPorDefecto => false,
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
        };
    }
}
