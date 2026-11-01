<?php

namespace App\Actions\Inscripciones;

use App\Avisos\Avisos;
use App\Competicion\Errores\OperacionNoPermitida;
use App\Enums\EstadoInscripcion;
use App\Enums\EstadoParticipante;
use App\Models\Inscripcion;
use App\Models\RegistroDeCambio;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Confirmar (respetando el cupo), mover a lista de espera o dar de baja.
 * Una baja retira al participante; al liberar plaza, la primera pareja en
 * espera no sube sola: lo decide la organización (o el aviso de la fase 3).
 */
final class CambiarEstadoDeInscripcion
{
    public function handle(Inscripcion $inscripcion, EstadoInscripcion $estado, ?User $usuario = null, bool $avisar = true): Inscripcion
    {
        $anterior = $inscripcion->estado;

        if ($anterior === $estado) {
            return $inscripcion;
        }

        if ($estado === EstadoInscripcion::Confirmada && InscribirPareja::alCupo($inscripcion->categoria)) {
            throw new OperacionNoPermitida('La categoría está al cupo: amplíelo o dé de baja a otra pareja antes.');
        }

        $resultado = DB::transaction(function () use ($inscripcion, $estado, $anterior, $usuario): Inscripcion {
            $inscripcion->fill([
                'estado' => $estado,
                'posicion_espera' => $estado === EstadoInscripcion::ListaDeEspera ? InscribirPareja::siguientePosicionDeEspera($inscripcion->categoria) : null,
                'confirmada_en' => $estado === EstadoInscripcion::Confirmada ? now() : $inscripcion->confirmada_en,
                'baja_en' => $estado === EstadoInscripcion::Baja ? now() : null,
            ])->save();

            $inscripcion->participante->update(['estado' => $estado === EstadoInscripcion::Baja ? EstadoParticipante::Retirado : EstadoParticipante::Activo]);

            if ($anterior === EstadoInscripcion::ListaDeEspera) {
                $this->recolocarLaEspera($inscripcion);
            }

            RegistroDeCambio::anotar('cambiar_inscripcion', $inscripcion, $usuario, ['estado' => $anterior->value], ['estado' => $estado->value]);

            return $inscripcion->refresh();
        });

        if ($avisar && $estado === EstadoInscripcion::Confirmada) {
            app(Avisos::class)->inscripcionConfirmada($resultado, desdeEspera: $anterior === EstadoInscripcion::ListaDeEspera);
        }

        return $resultado;
    }

    /** Las posiciones de la lista de espera quedan seguidas tras sacar a alguien. */
    private function recolocarLaEspera(Inscripcion $inscripcion): void
    {
        $enEspera = Inscripcion::query()->where('categoria_id', $inscripcion->categoria_id)->where('estado', EstadoInscripcion::ListaDeEspera->value)->whereKeyNot($inscripcion->id)->orderBy('posicion_espera')->get();

        foreach ($enEspera->values() as $i => $otra) {
            $otra->update(['posicion_espera' => $i + 1]);
        }
    }
}
