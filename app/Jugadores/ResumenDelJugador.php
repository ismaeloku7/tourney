<?php

namespace App\Jugadores;

use App\Enums\EstadoInscripcion;
use App\Enums\EstadoTorneo;
use App\Models\Inscripcion;
use App\Models\Jugador;
use App\Models\ParticipanteJugador;
use App\Models\Partido;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Lo que el inicio del portal enseña de un vistazo: sus inscripciones, el
 * próximo partido, la pareja del torneo más cercano, lo que tiene
 * pendiente y cuántos resultados esperan su validación.
 */
final class ResumenDelJugador
{
    public function __construct(private readonly PartidosDelJugador $partidos = new PartidosDelJugador) {}

    /**
     * Las inscripciones del jugador, las de torneos más próximos primero.
     *
     * @return Collection<int, Inscripcion>
     */
    public function inscripciones(Jugador $yo): Collection
    {
        return Inscripcion::query()
            ->whereHas('participante.participanteJugadores', fn (Builder $q) => $q->where('jugador_id', $yo->id))
            ->with(['categoria.torneo.sede', 'participante.participanteJugadores.jugador'])
            ->get()
            ->sortBy([fn (Inscripcion $a, Inscripcion $b): int => $this->prioridad($a) <=> $this->prioridad($b), fn (Inscripcion $a, Inscripcion $b): int => $a->categoria->torneo->fecha_inicio <=> $b->categoria->torneo->fecha_inicio])
            ->values();
    }

    /** @return Collection<int, Inscripcion> */
    public function activas(Jugador $yo): Collection
    {
        return $this->inscripciones($yo)->filter(fn (Inscripcion $i): bool => $this->activa($i))->values();
    }

    public function proximoPartido(Jugador $yo): ?Partido
    {
        return $this->partidos->proximo($yo);
    }

    /** La inscripción del torneo más cercano aún sin terminar, para la tarjeta de la pareja. */
    public function inscripcionMasProxima(Jugador $yo): ?Inscripcion
    {
        return $this->activas($yo)->first();
    }

    /** El compañero del jugador en una inscripción, si ya lo tiene. */
    public function companero(Inscripcion $inscripcion, Jugador $yo): ?ParticipanteJugador
    {
        return $inscripcion->participante->participanteJugadores->first(fn (ParticipanteJugador $f): bool => $f->jugador_id !== $yo->id);
    }

    public function porValidar(Jugador $yo): int
    {
        return $this->partidos->todos($yo)->filter(fn (Partido $p): bool => $this->partidos->fila($p, $yo)['puedeValidar'])->count();
    }

    /**
     * Lo que el jugador tiene que hacer, cada cosa con adónde ir.
     *
     * @return list<array{texto: string, detalle: string|null, url: string}>
     */
    public function pendientes(Jugador $yo): array
    {
        $pendientes = [];

        $porValidar = $this->porValidar($yo);
        if ($porValidar > 0) {
            $pendientes[] = ['texto' => $porValidar === 1 ? 'Validar el resultado que ha anotado tu rival' : "Validar {$porValidar} resultados que han anotado tus rivales", 'detalle' => null, 'url' => route('jugador.partidos')];
        }

        foreach ($this->activas($yo) as $inscripcion) {
            $companero = $this->companero($inscripcion, $yo);
            $torneo = $inscripcion->categoria->torneo;

            if ($companero === null) {
                $pendientes[] = ['texto' => 'Te falta pareja en '.$torneo->nombre, 'detalle' => 'Pide a la organización que la complete.', 'url' => route('jugador.inscripcion', $inscripcion)];
            } elseif ($companero->confirmado_en === null && $inscripcion->estado === EstadoInscripcion::Pendiente) {
                $pendientes[] = ['texto' => "Falta que {$companero->jugador->nombre} confirme la pareja", 'detalle' => $torneo->nombre.' · desde el correo que le ha llegado', 'url' => route('jugador.inscripcion', $inscripcion)];
            }

            if ($torneo->horarios_publicados_en === null && in_array($torneo->estado, [EstadoTorneo::InscripcionAbierta, EstadoTorneo::InscripcionCerrada, EstadoTorneo::Sorteo], true) && $yo->disponibilidades()->where('torneo_id', $torneo->id)->doesntExist()) {
                $pendientes[] = ['texto' => 'Dinos cuándo no puedes jugar en '.$torneo->nombre, 'detalle' => 'Antes de que salgan los horarios; si puedes siempre, nada que hacer.', 'url' => route('jugador.inscripcion', $inscripcion).'#disponibilidad'];
            }
        }

        if (blank($yo->nivel_declarado) && $this->activas($yo)->isNotEmpty()) {
            $pendientes[] = ['texto' => 'Pon tu nivel de juego', 'detalle' => 'Sirve para equilibrar los grupos.', 'url' => route('jugador.datos')];
        }

        return $pendientes;
    }

    private function activa(Inscripcion $inscripcion): bool
    {
        return $inscripcion->estado !== EstadoInscripcion::Baja
            && ! in_array($inscripcion->categoria->torneo->estado, [EstadoTorneo::Finalizado, EstadoTorneo::Archivado], true);
    }

    /** Primero las activas, luego las terminadas, al final las bajas. */
    private function prioridad(Inscripcion $inscripcion): int
    {
        return match (true) {
            $inscripcion->estado === EstadoInscripcion::Baja => 2,
            ! $this->activa($inscripcion) => 1,
            default => 0,
        };
    }
}
