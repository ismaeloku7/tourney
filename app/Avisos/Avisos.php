<?php

namespace App\Avisos;

use App\Enums\EstadoInscripcion;
use App\Enums\EstadoPartido;
use App\Filament\Competicion\Marcador;
use App\Models\Comunicacion;
use App\Models\Fase;
use App\Models\Inscripcion;
use App\Models\Jugador;
use App\Models\Participante;
use App\Models\Partido;
use App\Models\Torneo;
use App\Notifications\AvisoDeLaOrganizacion;
use App\Notifications\CruceAsignado;
use App\Notifications\HorariosPublicados;
use App\Notifications\InscripcionConfirmada;
use App\Notifications\ResultadoDelPartido;
use App\Notifications\ResultadoPorValidar;
use App\Notifications\SiguienteEnPista;
use App\Publico\DatosDelTorneo;
use App\Push\Push;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Notification;

/**
 * Quién recibe cada aviso del briefing. Las acciones lo llaman cuando pasa
 * algo; aquí se decide a qué jugadores (y seguidores del torneo) va. Los
 * envíos salen por la cola: un aviso por jugador, nunca uno por partido.
 */
class Avisos
{
    public function __construct(private Push $push) {}

    public function inscripcionConfirmada(Inscripcion $inscripcion, bool $desdeEspera = false): void
    {
        Notification::send($this->jugadoresDe($inscripcion->participante), new InscripcionConfirmada($inscripcion, $desdeEspera));
    }

    /** Uno por jugador inscrito, con sus partidos; y a quien sigue el torneo. */
    public function horariosPublicados(Torneo $torneo): void
    {
        $datos = new DatosDelTorneo($torneo);
        /** @var array<int, array{jugador: Jugador, partidos: list<string>}> $porJugador */
        $porJugador = [];

        foreach ($datos->partidosConHora()->get() as $partido) {
            $linea = $partido->franja->inicio->translatedFormat('l j, H:i').($partido->pista !== null ? ' · '.$partido->pista->nombre : '').' · '.Marcador::lado($partido, true).' – '.Marcador::lado($partido, false);

            foreach ([$partido->local, $partido->visitante] as $participante) {
                foreach ($participante !== null ? $this->jugadoresDe($participante) : [] as $jugador) {
                    $porJugador[$jugador->id] ??= ['jugador' => $jugador, 'partidos' => []];
                    $porJugador[$jugador->id]['partidos'][] = $linea;
                }
            }
        }

        foreach ($porJugador as $fila) {
            $fila['jugador']->notify(new HorariosPublicados($torneo, $fila['partidos']));
        }

        $this->push->aSeguidores($torneo, 'Horarios de '.$torneo->nombre, 'Ya están publicados los horarios.', '/torneos/'.$torneo->slug.'?ver=horarios', 'horarios-'.$torneo->id);
    }

    /** El rival tiene que validar lo que ha anotado un jugador. */
    public function resultadoPorValidar(Partido $partido): void
    {
        $registrador = $partido->registrado_por_jugador_id;
        $rival = $partido->local !== null && $registrador !== null && in_array($registrador, $partido->local->idsDeJugadores(), true)
            ? $partido->visitante
            : $partido->local;

        if ($rival !== null) {
            Notification::send($this->jugadoresDe($rival), new ResultadoPorValidar($partido));
        }
    }

    /** El resultado ya cuenta: a las dos parejas y a quien sigue el torneo. */
    public function resultadoDelPartido(Partido $partido): void
    {
        $jugadores = [];
        foreach ([$partido->local, $partido->visitante] as $participante) {
            if ($participante !== null) {
                $jugadores = [...$jugadores, ...$this->jugadoresDe($participante)];
            }
        }

        $aviso = new ResultadoDelPartido($partido);
        Notification::send($jugadores, $aviso);

        $this->push->aSeguidores($partido->fase->categoria->torneo, $aviso->titulo(), $aviso->texto(), $aviso->url(), $aviso->etiqueta());
    }

    /** Tras cerrar una fase: cada pareja que pasa al cuadro (o a la consolación), con su primer cruce. */
    public function crucesDe(Fase $faseCerrada): void
    {
        $avisados = [];

        $partidos = Partido::query()
            ->whereHas('fase', fn (Builder $q) => $q->where('fase_origen_id', $faseCerrada->id))
            ->with(['fase.categoria.torneo', 'local.jugadores', 'visitante.jugadores'])
            ->orderBy('ronda')->orderBy('posicion')->get();

        foreach ($partidos as $partido) {
            foreach ([$partido->local, $partido->visitante] as $participante) {
                if ($participante === null || isset($avisados[$participante->id])) {
                    continue;
                }

                $avisados[$participante->id] = true;
                Notification::send($this->jugadoresDe($participante), new CruceAsignado($partido));
            }
        }
    }

    /**
     * A la pareja que juega después en la pista de `$partido`: que el
     * anterior empieza, que va por el último set, o que les toca. Cada
     * motivo una sola vez por partido; sin siguiente, nada.
     */
    public function siguienteEnPista(Partido $partido, string $motivo): void
    {
        $siguiente = self::siguienteDe($partido);
        if ($siguiente === null) {
            return;
        }

        $avisos = $siguiente->avisos_siguiente ?? [];
        if (isset($avisos[$motivo])) {
            return;
        }

        $siguiente->forceFill(['avisos_siguiente' => [...$avisos, $motivo => now()->toIso8601String()]])->saveQuietly();

        $jugadores = [];
        foreach ([$siguiente->local, $siguiente->visitante] as $participante) {
            if ($participante !== null) {
                $jugadores = [...$jugadores, ...$this->jugadoresDe($participante)];
            }
        }

        Notification::send($jugadores, new SiguienteEnPista($siguiente, $partido, $motivo));
    }

    /** El partido que va después en la misma pista y el mismo día, aún sin jugar. */
    public static function siguienteDe(Partido $partido): ?Partido
    {
        if ($partido->pista_id === null || $partido->orden_en_pista === null || $partido->franja === null) {
            return null;
        }

        return Partido::query()
            ->where('pista_id', $partido->pista_id)
            ->where('orden_en_pista', '>', $partido->orden_en_pista)
            ->whereHas('franja', fn (Builder $q) => $q->whereDate('inicio', $partido->franja->inicio->toDateString()))
            ->whereIn('estado', [EstadoPartido::Pendiente->value, EstadoPartido::Programado->value])
            ->whereNotNull('local_id')->whereNotNull('visitante_id')
            ->with(['local.jugadores', 'visitante.jugadores', 'pista', 'fase.categoria.torneo'])
            ->orderBy('orden_en_pista')
            ->first();
    }

    /** Un aviso libre: a los inscritos (no de baja) del torneo o de su categoría. Devuelve a cuántos jugadores. */
    public function comunicacion(Comunicacion $comunicacion): int
    {
        $inscripciones = Inscripcion::query()
            ->whereHas('categoria', fn (Builder $q) => $q->where('torneo_id', $comunicacion->torneo_id))
            ->when($comunicacion->categoria_id !== null, fn (Builder $q) => $q->where('categoria_id', $comunicacion->categoria_id))
            ->whereIn('estado', [EstadoInscripcion::Confirmada->value, EstadoInscripcion::Pendiente->value, EstadoInscripcion::ListaDeEspera->value])
            ->with('participante.jugadores')
            ->get();

        $jugadores = [];
        foreach ($inscripciones as $inscripcion) {
            foreach ($this->jugadoresDe($inscripcion->participante) as $jugador) {
                $jugadores[$jugador->id] = $jugador;
            }
        }

        Notification::send(array_values($jugadores), new AvisoDeLaOrganizacion($comunicacion));

        return count($jugadores);
    }

    /** @return list<Jugador> */
    private function jugadoresDe(Participante $participante): array
    {
        return array_values($participante->jugadores()->whereNull('anonimizado_en')->get()->all());
    }
}
