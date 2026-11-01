<?php

namespace App\Actions\Jugadores;

use App\Competicion\Errores\OperacionNoPermitida;
use App\Enums\EstadoInscripcion;
use App\Models\Disponibilidad;
use App\Models\Inscripcion;
use App\Models\Jugador;
use App\Models\Torneo;
use Carbon\CarbonImmutable;
use DateTimeImmutable;

/**
 * «Ese rato no puedo jugar»: un intervalo dentro de los días del torneo
 * que el planificador respeta. Solo antes de que se publiquen los horarios.
 */
final class AnotarNoDisponibilidad
{
    /** @throws OperacionNoPermitida */
    public function handle(Jugador $jugador, Torneo $torneo, string $dia, string $desde, string $hasta): Disponibilidad
    {
        self::comprobar($jugador, $torneo);

        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $dia);
        $horas = '/^([01]\d|2[0-3]):[0-5]\d$/';

        if ($fecha === false || $fecha->format('Y-m-d') !== $dia || preg_match($horas, $desde) !== 1 || preg_match($horas, $hasta) !== 1) {
            throw new OperacionNoPermitida('El día o las horas no son válidos.');
        }

        $inicio = CarbonImmutable::parse("{$dia} {$desde}");
        $fin = CarbonImmutable::parse("{$dia} {$hasta}");

        if ($fin->lte($inicio)) {
            throw new OperacionNoPermitida('La hora de fin tiene que ir después de la de inicio.');
        }

        if ($inicio->lt($torneo->fecha_inicio->startOfDay()) || $fin->gt($torneo->fecha_fin->endOfDay())) {
            throw new OperacionNoPermitida('El intervalo tiene que caer en los días del torneo.');
        }

        return Disponibilidad::query()->create(['jugador_id' => $jugador->id, 'torneo_id' => $torneo->id, 'desde' => $inicio, 'hasta' => $fin]);
    }

    /** @throws OperacionNoPermitida */
    public static function comprobar(Jugador $jugador, Torneo $torneo): void
    {
        if ($torneo->horarios_publicados_en !== null) {
            throw new OperacionNoPermitida('Los horarios ya están publicados: para cambios, escribe a la organización.');
        }

        $inscrito = Inscripcion::query()
            ->whereHas('categoria', fn ($q) => $q->where('torneo_id', $torneo->id))
            ->where('estado', '!=', EstadoInscripcion::Baja->value)
            ->whereHas('participante.participanteJugadores', fn ($q) => $q->where('jugador_id', $jugador->id))
            ->exists();

        if (! $inscrito) {
            throw new OperacionNoPermitida('No estás inscrito en este torneo.');
        }
    }
}
