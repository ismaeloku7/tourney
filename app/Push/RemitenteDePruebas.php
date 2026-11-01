<?php

namespace App\Push;

use App\Models\SuscripcionPush;

/** Para las pruebas: apunta lo que se habría mandado y a quién. */
class RemitenteDePruebas implements Remitente
{
    /** @var list<array{suscripcion: int, carga: array<string, mixed>}> */
    public array $enviados = [];

    public function enviar(SuscripcionPush $suscripcion, string $carga): ?bool
    {
        $datos = json_decode($carga, true);
        $this->enviados[] = ['suscripcion' => $suscripcion->id, 'carga' => is_array($datos) ? $datos : []];

        return true;
    }

    /** @return list<string> */
    public function titulos(): array
    {
        return array_values(array_map(fn (array $e): string => (string) ($e['carga']['titulo'] ?? ''), $this->enviados));
    }
}
