<?php

namespace App\Actions\Competicion;

use App\Competicion\Datos\PartidoEnDirecto;
use App\Competicion\Datos\SugerenciaDeRecolocacion;
use App\Competicion\Errores\OperacionNoPermitida;
use App\Competicion\PistasEnDirecto;
use App\Enums\EstadoPartido;
use App\Enums\EstadoTorneo;
use App\Filament\Competicion\Marcador;
use App\Models\Jugador;
use App\Models\Partido;
use App\Models\RegistroDeCambio;
use App\Models\Torneo;
use App\Models\User;
use App\Notifications\ACalentar;
use App\Notifications\AvisoAlJugador;
use App\Notifications\CambioDePista;
use App\Notifications\NuevaHoraEstimada;
use App\Notifications\SoisLosSiguientes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Los horarios durante el torneo (en juego y con los horarios
 * publicados). Tras cada resultado, la hora estimada de lo que falta se
 * recalcula (la pista que acaba antes llama ya al siguiente; la que se
 * alarga empuja a los suyos), y a la cola de esa pista se le avisa: a la
 * primera pareja que pase a calentar, a la segunda que es la siguiente.
 * Si una pista va con retraso, propone pasar uno de sus partidos a otra
 * pista, con tiempo para avisar; la organización decide si se aplica.
 */
final class HorasEnDirecto
{
    /** Minutos de retraso a partir de los que una pista se da por atrasada. */
    public const RETRASO_MINUTOS = 20;

    /** Minutos de aviso como mínimo para mover un partido de pista. */
    public const AVISO_MINUTOS = 30;

    /** A partir de cuántos minutos de cambio en su hora estimada se avisa a una pareja al recolocar. */
    public const CAMBIO_QUE_SE_AVISA = 15;

    /** A partir de cuántos minutos de cambio, contra lo último que se les dijo, se avisa al encadenar. */
    public const AVISO_CAMBIO_MINUTOS = 5;

    /** Minutos de margen antes de dar por atascada una pista cuyo partido no ha empezado a su hora. */
    public const TOLERANCIA_MINUTOS = 5;

    public function __construct(private readonly PistasEnDirecto $motor = new PistasEnDirecto) {}

    public static function activo(Torneo $torneo): bool
    {
        return $torneo->estado === EstadoTorneo::EnJuego && $torneo->horarios_publicados_en !== null;
    }

    /**
     * Recalcula la hora estimada de lo que falta y la guarda. Devuelve los
     * partidos que cambian: id → [inicio antes, inicio ahora].
     *
     * @return array<int, array{0: int, 1: int}>
     */
    public function recalcular(Torneo $torneo): array
    {
        if (! self::activo($torneo)) {
            return [];
        }

        [$datos, $modelos] = $this->cargar($torneo);

        return $this->guardar($this->motor->recalcular($datos, now()->getTimestamp(), $torneo->descanso_minimo_minutos, $torneo->margen_entre_partidos_minutos), $modelos);
    }

    /**
     * Al empezar un partido: el que seguía en juego en su pista (sin
     * resultado todavía) ha dejado la pista a esta hora justa. Lo de detrás
     * se recalcula y se avisa a quien cambia de hora.
     */
    public function trasEmpezar(Partido $partido): void
    {
        $torneo = $partido->fase->categoria->torneo;

        if (! self::activo($torneo)) {
            return;
        }

        if ($partido->pista_id !== null) {
            Partido::query()
                ->where('pista_id', $partido->pista_id)
                ->whereKeyNot($partido->id)
                ->where('estado', EstadoPartido::EnJuego->value)
                ->whereNull('terminado_en')
                ->whereHas('fase.categoria', fn (Builder $q) => $q->where('torneo_id', $torneo->id))
                ->update(['terminado_en' => now()]);
        }

        $this->recalcular($torneo);
        $this->avisarCambiosDeHora($torneo);
    }

    /** Tras un resultado: recalcula y avisa a la cola de esa pista. */
    public function trasResultado(Partido $partido): void
    {
        $torneo = $partido->fase->categoria->torneo;

        if (! self::activo($torneo)) {
            return;
        }

        // Antes de mover nada: su hora de inicio es cuando entró en pista.
        app(DuracionesAprendidas::class)->medir($partido);

        $this->recalcular($torneo);

        if ($partido->pista_id === null) {
            return;
        }

        $cola = Partido::query()
            ->where('pista_id', $partido->pista_id)
            ->whereIn('estado', [EstadoPartido::Pendiente->value, EstadoPartido::Programado->value, EstadoPartido::EnJuego->value])
            // Lo que ya dejó la pista (empezó el siguiente) no está en la cola.
            ->whereNull('terminado_en')
            ->whereNotNull('franja_id')
            ->whereHas('fase.categoria', fn (Builder $q) => $q->where('torneo_id', $torneo->id))
            ->with(['franja', 'pista', 'fase', 'local.jugadores', 'visitante.jugadores'])
            ->get()
            ->sortBy(fn (Partido $p): int => $p->franja !== null ? $p->franja->inicio->getTimestamp() : PHP_INT_MAX)
            ->values();

        foreach ([0 => 'aviso_calentar_en', 1 => 'aviso_siguientes_en'] as $posicion => $campo) {
            $siguiente = $cola->get($posicion);
            if ($siguiente === null || $siguiente->getAttribute($campo) !== null || ! $siguiente->fase->visible_en_web) {
                continue;
            }

            $siguiente->forceFill([$campo => now()])->save();
            $this->avisar($siguiente, $posicion === 0 ? new ACalentar($siguiente) : new SoisLosSiguientes($siguiente));
        }

        // A los de detrás cuya hora cambia, también (los dos primeros ya saben que les toca).
        $this->avisarCambiosDeHora($torneo, $cola->take(2)->pluck('id')->map(fn (mixed $id): int => (int) $id)->all());
    }

    /**
     * Avisa (push) a las parejas de lo que falta cuya hora estimada se ha
     * movido bastante desde la última que se les dijo (o la planificada).
     *
     * @param  list<int>  $yaAvisados  Partidos que ya tienen su aviso: solo se anota su hora.
     */
    private function avisarCambiosDeHora(Torneo $torneo, array $yaAvisados = []): void
    {
        $partidos = Partido::query()
            ->whereHas('fase.categoria', fn (Builder $q) => $q->where('torneo_id', $torneo->id))
            ->whereIn('estado', [EstadoPartido::Pendiente->value, EstadoPartido::Programado->value])
            ->whereNotNull('franja_id')
            ->with(['franja', 'pista', 'fase', 'local.jugadores', 'visitante.jugadores'])
            ->get();

        foreach ($partidos as $partido) {
            $franja = $partido->franja;
            $dicho = $partido->inicio_avisado ?? $franja?->inicio_previsto;
            if ($franja === null || $dicho === null || $franja->inicio->isPast()
                || abs($franja->inicio->getTimestamp() - $dicho->getTimestamp()) < self::AVISO_CAMBIO_MINUTOS * 60) {
                continue;
            }

            $partido->forceFill(['inicio_avisado' => $franja->inicio])->save();
            if (! in_array($partido->id, $yaAvisados, true)) {
                $this->avisar($partido, new NuevaHoraEstimada($partido));
            }
        }
    }

    /**
     * Las pistas atascadas: su partido debía haber empezado hace más de
     * TOLERANCIA_MINUTOS y no ha empezado (el anterior sigue sin cerrarse o
     * nadie ha pulsado «Empezar»). Uno por pista, el primero.
     *
     * @param  list<int>|null  $pistas  Solo estas pistas; nulo, todas.
     * @return list<array{partido: int, pista: string, texto: string, hora: string, minutos: int}>
     */
    public static function atascos(Torneo $torneo, ?array $pistas = null): array
    {
        if ($torneo->estado !== EstadoTorneo::EnJuego) {
            return [];
        }

        $limite = now()->subMinutes(self::TOLERANCIA_MINUTOS);
        $partidos = Partido::query()
            ->whereHas('fase.categoria', fn (Builder $q) => $q->where('torneo_id', $torneo->id))
            ->whereIn('partidos.estado', [EstadoPartido::Pendiente->value, EstadoPartido::Programado->value])
            ->whereNotNull('partidos.pista_id')
            ->when($pistas !== null, fn (Builder $q) => $q->whereIn('partidos.pista_id', $pistas ?? []))
            ->join('franjas', 'franjas.id', '=', 'partidos.franja_id')
            ->where('franjas.inicio', '<=', $limite)
            ->whereDate('franjas.inicio', now()->toDateString())
            ->orderBy('franjas.inicio')
            ->select('partidos.*')
            ->with(['franja', 'pista', 'local', 'visitante'])
            ->get()
            ->unique('pista_id');

        return array_values($partidos->map(fn (Partido $p): array => [
            'partido' => $p->id,
            'pista' => $p->pista->nombre ?? '',
            'texto' => Marcador::lado($p, true).' – '.Marcador::lado($p, false),
            'hora' => $p->franja?->inicio->format('H:i') ?? '',
            'minutos' => $p->franja !== null ? (int) $p->franja->inicio->diffInMinutes(now()) : 0,
        ])->all());
    }

    /** La recolocación que propone ahora, si alguna pista va atrasada. */
    public function sugerencia(Torneo $torneo): ?SugerenciaDeRecolocacion
    {
        if (! self::activo($torneo)) {
            return null;
        }

        [$datos] = $this->cargar($torneo);

        return $this->motor->sugerencia($datos, now()->getTimestamp(), $torneo->descanso_minimo_minutos, self::RETRASO_MINUTOS, self::AVISO_MINUTOS, $torneo->margen_entre_partidos_minutos);
    }

    /**
     * Cómo va cada pista: su retraso, lo que se juega y lo siguiente.
     *
     * @return list<array{pista: string, retraso: int|null, enPista: string|null, siguiente: string|null, hora: string|null, quedan: int}>
     */
    public function estado(Torneo $torneo): array
    {
        [$datos, $modelos] = $this->cargar($torneo);
        $ahora = now()->getTimestamp();
        $horas = $this->motor->recalcular($datos, $ahora, $torneo->descanso_minimo_minutos, $torneo->margen_entre_partidos_minutos);
        $retrasos = $this->motor->retrasos($datos, $horas, $ahora);
        $salida = [];

        foreach ($torneo->torneoPistas()->with('pista')->orderBy('orden')->get() as $torneoPista) {
            $cola = array_values(array_filter($datos, fn (PartidoEnDirecto $p): bool => ! $p->cerrado && $p->pista === $torneoPista->pista_id && isset($horas[$p->id])));
            usort($cola, fn (PartidoEnDirecto $a, PartidoEnDirecto $b): int => $horas[$a->id][0] <=> $horas[$b->id][0]);

            $enPista = $cola !== [] && $horas[$cola[0]->id][0] <= $ahora ? array_shift($cola) : null;
            $siguiente = $cola[0] ?? null;
            $nombre = fn (PartidoEnDirecto $p): string => ($m = $modelos->get($p->id)) !== null ? Marcador::lado($m, true).' – '.Marcador::lado($m, false) : 'P'.$p->id;

            $salida[] = [
                'pista' => $torneoPista->pista->nombre,
                'retraso' => $retrasos[$torneoPista->pista_id] ?? null,
                'enPista' => $enPista !== null ? $nombre($enPista) : null,
                'siguiente' => $siguiente !== null ? $nombre($siguiente) : null,
                'hora' => $siguiente !== null ? now()->setTimestamp($horas[$siguiente->id][0])->format('H:i') : null,
                'quedan' => count($cola) + ($enPista !== null ? 1 : 0),
            ];
        }

        return $salida;
    }

    /**
     * Aplica una recolocación si sigue siendo la que se propone: el partido
     * cambia de pista, las horas se recalculan y se avisa a sus parejas y
     * a las que cambian bastante de hora.
     *
     * @throws OperacionNoPermitida
     */
    public function aplicar(Torneo $torneo, string $clave, User $usuario): void
    {
        $sugerencia = $this->sugerencia($torneo);

        if ($sugerencia === null || $sugerencia->clave() !== $clave) {
            throw new OperacionNoPermitida('Las pistas han cambiado desde que se propuso: mira la sugerencia de ahora.');
        }

        [$datos, $modelos] = $this->cargar($torneo);
        $ahora = now()->getTimestamp();
        $antes = $this->motor->recalcular($datos, $ahora, $torneo->descanso_minimo_minutos, $torneo->margen_entre_partidos_minutos);
        $movidos = array_map(fn (PartidoEnDirecto $p): PartidoEnDirecto => $p->id === $sugerencia->partido
            ? $p->con($sugerencia->haciaPista, $p->inicio, $sugerencia->orden, $ahora + self::AVISO_MINUTOS * 60)
            : $p, $datos);
        $despues = $this->motor->recalcular($movidos, $ahora, $torneo->descanso_minimo_minutos, $torneo->margen_entre_partidos_minutos);

        $partido = $modelos->get($sugerencia->partido);
        $franja = $partido?->franja;
        if ($partido === null || $franja === null) {
            throw new OperacionNoPermitida('Ese partido ya no tiene pista.');
        }

        DB::transaction(function () use ($partido, $franja, $sugerencia, $despues, $modelos, $usuario): void {
            $desde = $partido->pista_id;
            $franja->forceFill(['pista_id' => $sugerencia->haciaPista])->save();
            $partido->forceFill(['pista_id' => $sugerencia->haciaPista, 'aviso_siguientes_en' => null, 'aviso_calentar_en' => null, 'aviso_proximo_en' => null])->save();

            $this->guardar($despues, $modelos);

            // Su nueva hora es la que se le promete ahora.
            $franja->refresh();
            $franja->forceFill(['inicio_previsto' => $franja->inicio])->save();

            RegistroDeCambio::anotar('recolocar_partido', $partido, $usuario, ['pista_id' => $desde], ['pista_id' => $sugerencia->haciaPista, 'inicio' => $franja->inicio->format('Y-m-d H:i')]);
        });

        $partido->refresh()->load(['franja', 'pista', 'local.jugadores', 'visitante.jugadores']);
        $this->avisar($partido, new CambioDePista($partido));

        foreach ($despues as $id => [$inicio]) {
            $otro = $modelos->get($id);
            if ($id === $partido->id || $otro === null || ! isset($antes[$id]) || abs($inicio - $antes[$id][0]) < self::CAMBIO_QUE_SE_AVISA * 60 || ! $otro->fase->visible_en_web) {
                continue;
            }
            $otro->refresh()->load(['franja', 'pista', 'local.jugadores', 'visitante.jugadores']);
            $this->avisar($otro, new NuevaHoraEstimada($otro));
        }
    }

    /**
     * Los partidos del torneo con pista y hora, como los ve el motor, y
     * sus modelos por id.
     *
     * @return array{0: list<PartidoEnDirecto>, 1: Collection<int, Partido>}
     */
    private function cargar(Torneo $torneo): array
    {
        $modelos = Partido::query()
            ->whereHas('fase.categoria', fn (Builder $q) => $q->where('torneo_id', $torneo->id))
            ->where('estado', '!=', EstadoPartido::Anulado->value)
            ->whereNotNull('franja_id')
            ->with(['franja', 'fase', 'local.participanteJugadores', 'visitante.participanteJugadores', 'local', 'visitante'])
            ->get()
            ->keyBy('id');

        $datos = [];
        foreach ($modelos as $partido) {
            $franja = $partido->franja;
            if ($franja === null) {
                continue;
            }

            $conResultado = $partido->cerrado() || $partido->estado === EstadoPartido::PendienteValidacion;
            // Dejó la pista sin resultado todavía: empezó el siguiente de su pista.
            $terminado = $partido->terminado_en !== null;
            $cerrado = $conResultado || $terminado;
            // Se está jugando: desde que empezó, con lo que le queda de juego (sin calentamiento).
            $empezado = ! $cerrado && $partido->estado === EstadoPartido::EnJuego && $partido->empezado_en !== null;
            $minutos = max(1, intdiv($franja->fin->getTimestamp() - $franja->inicio->getTimestamp(), 60));
            $dependeDe = [];
            foreach ([$partido->origenLocalDto(), $partido->origenVisitanteDto()] as $origen) {
                if ($origen !== null && $origen->apuntaA($origen->referencia) && $modelos->has($origen->referencia)) {
                    $dependeDe[] = $origen->referencia;
                }
            }

            $datos[] = new PartidoEnDirecto(
                $partido->id,
                $franja->pista_id,
                $empezado && $partido->empezado_en !== null ? $partido->empezado_en->getTimestamp() : $franja->inicio->getTimestamp(),
                $empezado ? max(1, $minutos - $torneo->margen_entre_partidos_minutos) : $minutos,
                array_values(array_unique([...($partido->local?->idsDeJugadores() ?? []), ...($partido->visitante?->idsDeJugadores() ?? [])])),
                $dependeDe,
                $cerrado,
                // Acabó cuando dejó la pista: al empezar el siguiente o, si no, al anotar el resultado.
                $cerrado ? ($partido->terminado_en ?? $partido->registrado_en)?->getTimestamp() : null,
                $franja->inicio_previsto?->getTimestamp(),
                empezado: $empezado,
            );
        }

        return [$datos, $modelos];
    }

    /**
     * Guarda las horas que cambian (un minuto o más). Solo se mueve el
     * partido: su duración prevista no cambia aunque el que está en pista
     * se alargue (eso solo empuja a los siguientes).
     *
     * @param  array<int, array{0: int, 1: int}>  $horas
     * @param  Collection<int, Partido>  $modelos
     * @return array<int, array{0: int, 1: int}>
     */
    private function guardar(array $horas, Collection $modelos): array
    {
        $cambios = [];

        foreach ($horas as $id => [$inicio]) {
            $partido = $modelos->get($id);
            $franja = $partido?->franja;
            // Lo que se está jugando se queda en su franja: su hora real es la de «Empezar».
            if ($partido === null || $franja === null || ($partido->estado === EstadoPartido::EnJuego && $partido->empezado_en !== null)) {
                continue;
            }

            $antes = $franja->inicio->getTimestamp();
            if (abs($antes - $inicio) < 60) {
                continue;
            }

            $duracion = $franja->fin->getTimestamp() - $antes;
            $franja->forceFill(['inicio' => now()->setTimestamp($inicio)->second(0), 'fin' => now()->setTimestamp($inicio + $duracion)->second(0)])->save();
            $cambios[$id] = [$antes, $inicio];
        }

        return $cambios;
    }

    /** A los cuatro jugadores; de una fase oculta en la web, a nadie. */
    private function avisar(Partido $partido, AvisoAlJugador $aviso): void
    {
        if ($partido->local === null || $partido->visitante === null || ! $partido->fase->visible_en_web) {
            return;
        }

        $jugadores = $partido->local->jugadores->merge($partido->visitante->jugadores)
            ->filter(fn (Jugador $j): bool => $j->anonimizado_en === null)
            ->values()
            ->all();

        Notification::send($jugadores, $aviso);
    }
}
