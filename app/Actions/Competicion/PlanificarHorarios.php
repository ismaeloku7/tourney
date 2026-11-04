<?php

namespace App\Actions\Competicion;

use App\Competicion\Datos\DisponibilidadDePista;
use App\Competicion\Datos\Intervalo;
use App\Competicion\Datos\OcupacionFija;
use App\Competicion\Datos\PartidoAPlanificar;
use App\Competicion\Datos\PlanDePistas;
use App\Competicion\Datos\RestriccionesDePlanificacion;
use App\Competicion\Errores\ConfiguracionInvalida;
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

    public function handle(Torneo $torneo, User $usuario, bool $rehacer = false): PlanDePistas
    {
        $todos = Partido::query()
            ->whereHas('fase.categoria', fn (Builder $consulta) => $consulta->where('torneo_id', $torneo->id))
            ->where('estado', '!=', EstadoPartido::Anulado->value)
            ->with(['fase.categoria', 'local.participanteJugadores', 'visitante.participanteJugadores', 'franja'])
            ->get()
            ->keyBy('id');

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
                $partido->formatoEfectivo()->duracion_estimada,
                $dependeDe,
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
            ),
            $fijos,
        );

        DB::transaction(function () use ($torneo, $usuario, $plan, $aPlanificar, $todos): void {
            // Los que se replanifican sueltan su hora de antes; y fuera las franjas vacías.
            foreach ($aPlanificar as $dto) {
                $partido = $todos->get($dto->id);
                if ($partido !== null && $partido->franja_id !== null) {
                    $franja = $partido->franja_id;
                    $partido->forceFill(['franja_id' => null, 'pista_id' => null, 'estado' => $partido->estado === EstadoPartido::Programado ? EstadoPartido::Pendiente : $partido->estado])->save();
                    Franja::query()->whereKey($franja)->delete();
                }
            }
            Franja::query()->where('torneo_id', $torneo->id)->where('bloqueada', false)->whereDoesntHave('partido')->delete();

            foreach ($plan->colocados as $colocado) {
                $franja = $torneo->franjas()->create([
                    'pista_id' => $colocado->pista,
                    'inicio' => $colocado->inicio,
                    'fin' => $colocado->fin,
                    'bloqueada' => false,
                ]);

                Partido::query()->whereKey($colocado->partido)->update([
                    'franja_id' => $franja->id,
                    'pista_id' => $colocado->pista,
                    'estado' => EstadoPartido::Programado->value,
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

        return $plan;
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
