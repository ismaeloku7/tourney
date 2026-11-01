<?php

namespace App\Actions\Inscripciones;

use App\Competicion\Errores\OperacionNoPermitida;
use App\Enums\EstadoInscripcion;
use App\Enums\EstadoParticipante;
use App\Enums\OrigenInscripcion;
use App\Models\Categoria;
use App\Models\Inscripcion;
use App\Models\Jugador;
use App\Models\Participante;
use App\Models\RegistroDeCambio;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Inscribe a una pareja en una categoría: crea el participante con sus
 * dos jugadores y su inscripción. Si la categoría está al cupo, la deja
 * en lista de espera con su posición. La misma pareja no se inscribe dos
 * veces en la misma categoría, y un jugador no va en dos parejas de la
 * misma categoría.
 */
final class InscribirPareja
{
    public function handle(Categoria $categoria, Jugador $primero, Jugador $segundo, OrigenInscripcion $origen, ?User $registradaPor = null, bool $confirmada = true, ?string $notas = null): Inscripcion
    {
        if ($primero->is($segundo)) {
            throw new OperacionNoPermitida('Una pareja son dos jugadores distintos.');
        }

        foreach ([$primero, $segundo] as $jugador) {
            $yaInscrito = Participante::query()
                ->where('categoria_id', $categoria->id)
                ->whereHas('inscripcion', fn ($consulta) => $consulta->whereIn('estado', [EstadoInscripcion::Pendiente->value, EstadoInscripcion::Confirmada->value, EstadoInscripcion::ListaDeEspera->value]))
                ->whereHas('participanteJugadores', fn ($consulta) => $consulta->where('jugador_id', $jugador->id))
                ->exists();

            if ($yaInscrito) {
                throw new OperacionNoPermitida("{$jugador->nombre_completo} ya está inscrito en esta categoría.");
            }
        }

        return DB::transaction(function () use ($categoria, $primero, $segundo, $origen, $registradaPor, $confirmada, $notas): Inscripcion {
            $participante = Participante::query()->create([
                'categoria_id' => $categoria->id,
                'nombre' => Participante::nombreDesdeJugadores($primero, $segundo),
                'estado' => EstadoParticipante::Activo,
            ]);

            foreach ([$primero, $segundo] as $orden => $jugador) {
                $participante->participanteJugadores()->create(['jugador_id' => $jugador->id, 'orden' => $orden + 1, 'confirmado_en' => now()]);
            }

            $hayCupo = ! self::alCupo($categoria);
            $estado = match (true) {
                ! $confirmada => EstadoInscripcion::Pendiente,
                $hayCupo => EstadoInscripcion::Confirmada,
                default => EstadoInscripcion::ListaDeEspera,
            };

            $inscripcion = Inscripcion::query()->create([
                'categoria_id' => $categoria->id,
                'participante_id' => $participante->id,
                'estado' => $estado,
                'posicion_espera' => $estado === EstadoInscripcion::ListaDeEspera ? self::siguientePosicionDeEspera($categoria) : null,
                'origen' => $origen,
                'notas' => $notas,
                'confirmada_en' => $estado === EstadoInscripcion::Confirmada ? now() : null,
                'registrada_por' => $registradaPor?->id,
            ]);

            RegistroDeCambio::anotar('inscribir', $inscripcion, $registradaPor, null, ['participante' => $participante->nombre, 'estado' => $estado->value]);

            return $inscripcion;
        });
    }

    public static function alCupo(Categoria $categoria): bool
    {
        return $categoria->cupo !== null
            && $categoria->inscripciones()->where('estado', EstadoInscripcion::Confirmada->value)->count() >= $categoria->cupo;
    }

    public static function siguientePosicionDeEspera(Categoria $categoria): int
    {
        return (int) $categoria->inscripciones()->where('estado', EstadoInscripcion::ListaDeEspera->value)->max('posicion_espera') + 1;
    }
}
