<?php

namespace App\Actions\Competicion;

use App\Competicion\Datos\DisponibilidadDePista;
use App\Competicion\Datos\Intervalo;
use App\Competicion\Datos\OcupacionFija;
use App\Competicion\Datos\PartidoAPlanificar;
use App\Competicion\Datos\PlanDePistas;
use App\Competicion\Datos\PropuestaDeHorarios;
use App\Competicion\Datos\RestriccionesDePlanificacion;
use App\Competicion\Errores\ConfiguracionInvalida;
use App\Competicion\Errores\OperacionNoPermitida;
use App\Competicion\PlanificadorContinuo;
use App\Enums\EstadoPartido;
use App\Models\Disponibilidad;
use App\Models\Franja;
use App\Models\Partido;
use App\Models\RegistroDeCambio;
use App\Models\Torneo;
use App\Models\User;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Pone pista y hora a los partidos pendientes de todas las categorías del
 * torneo con el planificador continuo: cada partido reserva su pista para
 * el calentamiento y su duración estimada, y el siguiente entra en cuanto
 * acaba. También los de rondas cuyas parejas aún no se saben, detrás de
 * los partidos que se las dan. Lo ya programado (o jugado) se respeta,
 * salvo que se pida rehacerlo: entonces se replanifica todo lo que no ha
 * empezado, desde ahora.
 */
final class PlanificarHorarios
{
    public function __construct(private readonly PlanificadorContinuo $planificador = new PlanificadorContinuo) {}

    /** Calcula y guarda en un paso. */
    public function handle(Torneo $torneo, User $usuario, bool $rehacer = false): PlanDePistas
    {
        $propuesta = $this->proponer($torneo, $rehacer);
        $this->aplicar($torneo, $propuesta, $usuario);

        return $propuesta->plan;
    }

    /** Calcula el plan sin guardar nada. */
    public function proponer(Torneo $torneo, bool $rehacer = false): PropuestaDeHorarios
    {
        $todos = Partido::query()
            ->whereHas('fase.categoria', fn (Builder $consulta) => $consulta->where('torneo_id', $torneo->id))
            ->where('estado', '!=', EstadoPartido::Anulado->value)
            ->with(['fase.categoria', 'local.participanteJugadores', 'visitante.participanteJugadores', 'local.jugadores', 'visitante.jugadores', 'franja'])
            ->get()
            ->keyBy('id');

        // Lo que duran de verdad los partidos ya jugados, mejor que la duración puesta a mano.
        $duraciones = app(DuracionesAprendidas::class);
        $estimador = $duraciones->estimador();

        $seMueve = fn (Partido $p): bool => $p->estado === EstadoPartido::Pendiente
            || ($rehacer && $p->estado === EstadoPartido::Programado);

        $fijos = [];
        $aPlanificar = [];

        foreach ($todos as $partido) {
            $jugadores = array_values(array_unique([...($partido->local?->idsDeJugadores() ?? []), ...($partido->visitante?->idsDeJugadores() ?? [])]));

            if (! $seMueve($partido) || ($partido->franja !== null && ! $rehacer)) {
                if ($partido->franja !== null) {
                    $fijos[] = new OcupacionFija($partido->franja->pista_id, $this->intervalo($partido->franja), $partido->id, $jugadores);
                }

                continue;
            }

            // De los partidos que dan sus parejas, los que aún no se han jugado van antes.
            $dependeDe = [];
            foreach ([$partido->origenLocalDto(), $partido->origenVisitanteDto()] as $origen) {
                if ($origen !== null && $origen->apuntaA($origen->referencia)) {
                    $previo = $todos->get($origen->referencia);
                    if ($previo !== null && ! $previo->cerrado()) {
                        $dependeDe[] = $previo->id;
                    }
                }
            }

            // Sin parejas y sin partidos de los que salgan, todavía no se puede colocar.
            if (($partido->local_id === null || $partido->visitante_id === null) && $dependeDe === []) {
                continue;
            }

            $categoria = $partido->fase->categoria;
            $aPlanificar[] = new PartidoAPlanificar(
                $partido->id,
                $categoria->id,
                $categoria->orden,
                $partido->ronda,
                $partido->jornada,
                $partido->local_id,
                $partido->visitante_id,
                $jugadores,
                $duraciones->minutosDe($partido, $estimador),
                $dependeDe,
                $partido->fase->tipo->esDeGrupos(),
            );
        }

        foreach (Franja::query()->where('torneo_id', $torneo->id)->where('bloqueada', true)->get() as $bloqueo) {
            $fijos[] = new OcupacionFija($bloqueo->pista_id, $this->intervalo($bloqueo));
        }

        $noDisponibilidades = [];
        foreach ($torneo->disponibilidades as $disponibilidad) {
            /** @var Disponibilidad $disponibilidad */
            $noDisponibilidades[$disponibilidad->jugador_id][] = new Intervalo(
                DateTimeImmutable::createFromInterface($disponibilidad->desde),
                DateTimeImmutable::createFromInterface($disponibilidad->hasta),
            );
        }

        $plan = $this->planificador->planificar(
            $aPlanificar,
            $this->pistas($torneo),
            new RestriccionesDePlanificacion(
                $torneo->descanso_minimo_minutos,
                $noDisponibilidades,
                $torneo->margen_entre_partidos_minutos,
                $rehacer ? CarbonImmutable::now()->ceilMinute(5)->toDateTimeImmutable() : null,
                array_values($torneo->torneoPistas()->whereHas('pista', fn (Builder $consulta) => $consulta->where('exterior', true))->pluck('pista_id')->map(fn (mixed $id): int => (int) $id)->all()),
            ),
            $fijos,
        );

        return new PropuestaDeHorarios($plan, array_map(fn (PartidoAPlanificar $p): int => $p->id, $aPlanificar), $this->huella($torneo), $rehacer);
    }

    /**
     * Guarda un plan calculado: los partidos que se (re)colocan sueltan su
     * hora de antes y reciben la nueva. Si el torneo ha cambiado desde que
     * se calculó (un resultado, una hora a mano, las pistas…), no se
     * aplica.
     *
     * @throws OperacionNoPermitida
     */
    public function aplicar(Torneo $torneo, PropuestaDeHorarios $propuesta, User $usuario): void
    {
        if ($propuesta->huella !== $this->huella($torneo)) {
            throw new OperacionNoPermitida('El torneo ha cambiado desde que se calculó el plan: vuelve a calcularlo.');
        }

        $plan = $propuesta->plan;

        DB::transaction(function () use ($torneo, $usuario, $plan, $propuesta): void {
            // Los que se replanifican sueltan su hora de antes; y fuera las franjas vacías.
            foreach (Partido::query()->whereIn('id', $propuesta->partidos)->whereNotNull('franja_id')->get() as $partido) {
                $franja = $partido->franja_id;
                $partido->forceFill(['franja_id' => null, 'pista_id' => null, 'estado' => $partido->estado === EstadoPartido::Programado ? EstadoPartido::Pendiente : $partido->estado])->save();
                Franja::query()->whereKey($franja)->delete();
            }
            Franja::query()->where('torneo_id', $torneo->id)->where('bloqueada', false)->whereDoesntHave('partido')->delete();

            foreach ($plan->colocados as $colocado) {
                $franja = $torneo->franjas()->create([
                    'pista_id' => $colocado->pista,
                    'inicio' => $colocado->inicio,
                    'fin' => $colocado->fin,
                    'inicio_previsto' => $colocado->inicio,
                    'bloqueada' => false,
                ]);

                Partido::query()->whereKey($colocado->partido)->update([
                    'franja_id' => $franja->id,
                    'pista_id' => $colocado->pista,
                    'estado' => EstadoPartido::Programado->value,
                    'aviso_siguientes_en' => null,
                    'aviso_calentar_en' => null,
                ]);
            }

            RegistroDeCambio::anotar('planificar_horarios', $torneo, $usuario, null, [
                'asignados' => count($plan->colocados),
                'fin' => $plan->fin?->format('Y-m-d H:i'),
                'ocupacion' => $plan->ocupacion,
                'minutos_muertos' => $plan->minutosMuertos,
                'conflictos' => array_map(fn ($c): array => ['partido' => $c->partido, 'tipo' => $c->tipo, 'detalle' => $c->detalle], $plan->conflictos),
            ]);
        });
    }

    /**
     * Lo que cambia el plan: los partidos (estado, parejas, hora), las
     * pistas y su disponibilidad, los bloqueos, el calentamiento y el
     * descanso.
     */
    public function huella(Torneo $torneo): string
    {
        $partidos = Partido::query()
            ->whereHas('fase.categoria', fn (Builder $consulta) => $consulta->where('torneo_id', $torneo->id))
            ->orderBy('id')
            ->get(['id', 'estado', 'franja_id', 'local_id', 'visitante_id'])
            ->map(fn (Partido $p): array => [$p->id, $p->estado->value, $p->franja_id, $p->local_id, $p->visitante_id])
            ->all();

        $fresco = $torneo->fresh() ?? $torneo;

        return md5((string) json_encode([
            $partidos,
            $fresco->torneoPistas()->orderBy('pista_id')->get(['pista_id', 'disponibilidad'])->toArray(),
            Franja::query()->where('torneo_id', $torneo->id)->where('bloqueada', true)->orderBy('id')->get(['pista_id', 'inicio', 'fin'])->toArray(),
            $fresco->margen_entre_partidos_minutos,
            $fresco->descanso_minimo_minutos,
        ]));
    }

    /**
     * Cada pista del torneo con los tramos en que está disponible.
     *
     * @return array<int, list<Intervalo>>
     */
    private function pistas(Torneo $torneo): array
    {
        $pistas = [];

        foreach ($torneo->torneoPistas as $torneoPista) {
            try {
                $disponibilidad = DisponibilidadDePista::desdeArray($torneoPista->disponibilidad);
            } catch (ConfiguracionInvalida) {
                continue;
            }

            foreach ($disponibilidad->tramos as $tramo) {
                $pistas[$torneoPista->pista_id][] = new Intervalo(
                    CarbonImmutable::parse($tramo->fecha.' '.$tramo->desde)->toDateTimeImmutable(),
                    CarbonImmutable::parse($tramo->fecha.' '.$tramo->hasta)->toDateTimeImmutable(),
                );
            }
        }

        return $pistas;
    }

    private function intervalo(Franja $franja): Intervalo
    {
        return new Intervalo(DateTimeImmutable::createFromInterface($franja->inicio), DateTimeImmutable::createFromInterface($franja->fin));
    }
}
