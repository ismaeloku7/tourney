<?php

namespace App\Enums;

use App\Enums\Concerns\ConEtiqueta;

/**
 * Lo que la base central cuenta de cada organizador para las métricas de
 * la plataforma. No sirve para aplicar límites de plan: esos se cuentan
 * en la base del organizador en el mismo request.
 */
enum TipoDeEventoDeUso: string
{
    use ConEtiqueta;

    case OrganizadorVerificado = 'organizador_verificado';
    case TorneoPublicado = 'torneo_publicado';
    case InscripcionConfirmada = 'inscripcion_confirmada';
    case PushEnviado = 'push_enviado';
    case CorreoEnviado = 'correo_enviado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::OrganizadorVerificado => 'Organizador verificado',
            self::TorneoPublicado => 'Torneo publicado',
            self::InscripcionConfirmada => 'Inscripción confirmada',
            self::PushEnviado => 'Push enviado',
            self::CorreoEnviado => 'Correo enviado',
        };
    }
}
