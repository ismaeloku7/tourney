<?php

namespace App\Actions\Inscripciones;

use App\Models\Inscripcion;
use App\Models\RegistroDeCambio;
use App\Models\User;

/** Anota que la pareja ha pagado (o deshace la anotación). Informativo. */
final class MarcarPago
{
    public function handle(Inscripcion $inscripcion, bool $pagado, ?string $notas, User $usuario): Inscripcion
    {
        $inscripcion->fill(['pago_marcado_en' => $pagado ? now() : null, 'pago_notas' => $notas])->save();

        RegistroDeCambio::anotar('marcar_pago', $inscripcion, $usuario, null, ['pagado' => $pagado, 'notas' => $notas]);

        return $inscripcion;
    }
}
