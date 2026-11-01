<?php

namespace App\Actions\Competicion;

use App\Competicion\Datos\FranjaDisponible;
use App\Competicion\Datos\Intervalo;
use App\Competicion\Datos\PartidoAPlanificar;
use App\Competicion\Datos\Planificacion;
use App\Competicion\Datos\RestriccionesDePlanificacion;
use App\Competicion\Planificador;
use App\Enums\EstadoPartido;
use App\Models\Disponibilidad;
use App\Models\Franja;
use App\Models\Partido;
use App\Models\RegistroDeCambio;
use App\Models\Torneo;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Pone hora y pista a los partidos pendientes de todas las categorías del
 * torneo con el planificador, respetando los ya programados, y guarda las
 * asignaciones. Devuelve la planificación con sus conflictos.
 */
final class PlanificarHorarios
{
    public function __construct(private readonly Planificador $planificador = new Planificador) {}

    public function handle(Torneo $torneo, User $usuario): Planificacion
    {
        $partidos = Partido::query()
            ->whereHas('fase.categoria', fn ($consulta) => $consulta->where('torneo_id', $torneo->id))
            ->whereIn('estado', [EstadoPartido::Pendiente->value, EstadoPartido::Programado->value])
            ->whereNotNull('local_id')
            ->whereNotNull('visitante_id')
            ->with(['fase.categoria', 'local.participanteJugadores', 'visitante.participanteJugadores', 'franja'])
            ->get();

        $franjasPorId = [];
        $yaFijados = [];
        $aPlanificar = [];

        foreach ($partidos as $partido) {
            $categoria = $partido->fase->categoria;
            $jugadores = [...$partido->local->idsDeJugadores(), ...$partido->visitante->idsDeJugadores()];
            $dependeDe = [];

            foreach ([$partido->origenLocalDto(), $partido->origenVisitanteDto()] as $origen) {
                if ($origen !== null && $origen->apuntaA($origen->referencia)) {
                    $previo = Partido::query()->find($origen->referencia);
                    if ($previo !== null && ! $previo->cerrado()) {
                        $dependeDe[] = $previo->id;
                    }
                }
            }

            $aPlanificar[] = new PartidoAPlanificar(
                $partido->id,
                $categoria->id,
                $categoria->orden,
                $partido->ronda,
                $partido->jornada,
                $partido->local_id,
                $partido->visitante_id,
                array_values(array_unique($jugadores)),
                $partido->formatoEfectivo()->duracion_estimada,
                $dependeDe,
            );

            if ($partido->franja !== null) {
                $yaFijados[$partido->id] = $this->franjaDto($partido->franja);
            }
        }

        $libres = $torneo->franjas()->where('bloqueada', false)->whereDoesntHave('partido')->get()
            ->map(fn (Franja $f): FranjaDisponible => $this->franjaDto($f))
            ->values()
            ->all();

        $noDisponibilidades = [];
        foreach ($torneo->disponibilidades as $disponibilidad) {
            /** @var Disponibilidad $disponibilidad */
            $noDisponibilidades[$disponibilidad->jugador_id][] = new Intervalo(
                DateTimeImmutable::createFromInterface($disponibilidad->desde),
                DateTimeImmutable::createFromInterface($disponibilidad->hasta),
            );
        }

        $planificacion = $this->planificador->planificar($aPlanificar, $libres, new RestriccionesDePlanificacion($torneo->descanso_minimo_minutos, $noDisponibilidades, $yaFijados));

        DB::transaction(function () use ($torneo, $usuario, $planificacion): void {
            foreach ($planificacion->asignaciones as $asignacion) {
                Partido::query()->whereKey($asignacion->partido)->update([
                    'franja_id' => $asignacion->franja->id,
                    'pista_id' => $asignacion->franja->pista,
                    'estado' => EstadoPartido::Programado->value,
                ]);
            }

            RegistroDeCambio::anotar('planificar_horarios', $torneo, $usuario, null, [
                'asignados' => count($planificacion->asignaciones),
                'conflictos' => array_map(fn ($c): array => ['partido' => $c->partido, 'tipo' => $c->tipo, 'detalle' => $c->detalle], $planificacion->conflictos),
            ]);
        });

        return $planificacion;
    }

    private function franjaDto(Franja $franja): FranjaDisponible
    {
        return new FranjaDisponible($franja->id, $franja->pista_id, DateTimeImmutable::createFromInterface($franja->inicio), DateTimeImmutable::createFromInterface($franja->fin));
    }
}
