<?php

namespace App\Actions\Jugadores;

use App\Models\Jugador;
use App\Models\Participante;
use Illuminate\Support\Facades\DB;

/**
 * El derecho de supresión desde el portal: se borran el nombre, el correo,
 * el teléfono y lo demás personal, y sus parejas pasan a llamarse con el
 * nombre anónimo. Los resultados se quedan, sin nadie detrás.
 */
final class AnonimizarJugador
{
    public function handle(Jugador $jugador): void
    {
        DB::transaction(function () use ($jugador): void {
            $jugador->forceFill([
                'nombre' => 'Jugador',
                'apellidos' => 'Anónimo',
                'email' => null,
                'telefono' => null,
                'fecha_nacimiento' => null,
                'alias' => null,
                'nivel_declarado' => null,
                'password' => null,
                'remember_token' => null,
                'avisos' => null,
                'anonimizado_en' => now(),
            ])->save();

            $jugador->enlacesMagicos()->delete();
            $jugador->disponibilidades()->delete();

            foreach (Participante::query()->whereHas('participanteJugadores', fn ($q) => $q->where('jugador_id', $jugador->id))->with('jugadores')->get() as $participante) {
                $participante->update(['nombre' => Participante::nombreDesdeJugadores(...$participante->jugadores->all())]);
            }
        });
    }
}
