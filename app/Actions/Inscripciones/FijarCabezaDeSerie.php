<?php

namespace App\Actions\Inscripciones;

use App\Competicion\Errores\OperacionNoPermitida;
use App\Models\Participante;
use App\Models\RegistroDeCambio;
use App\Models\User;

/** Da (o quita) un número de cabeza de serie a una pareja; no se repite dentro de la categoría. */
final class FijarCabezaDeSerie
{
    public function handle(Participante $participante, ?int $numero, User $usuario): Participante
    {
        if ($numero !== null && Participante::query()->where('categoria_id', $participante->categoria_id)->whereKeyNot($participante->id)->where('cabeza_de_serie', $numero)->exists()) {
            throw new OperacionNoPermitida("Ya hay una cabeza de serie número {$numero} en esta categoría.");
        }

        $antes = $participante->cabeza_de_serie;
        $participante->update(['cabeza_de_serie' => $numero]);

        RegistroDeCambio::anotar('cabeza_de_serie', $participante, $usuario, ['cabeza_de_serie' => $antes], ['cabeza_de_serie' => $numero]);

        return $participante;
    }
}
