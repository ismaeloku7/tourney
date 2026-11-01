<?php

namespace App\Actions\Competicion;

use App\Competicion\Datos\Participante as ParticipanteDto;
use App\Competicion\Datos\RepartoDeGrupos;
use App\Competicion\Errores\FaseConResultados;
use App\Competicion\Errores\OperacionNoPermitida;
use App\Competicion\RepartidorDeGrupos;
use App\Enums\EstadoFase;
use App\Enums\EstadoInscripcion;
use App\Enums\EstadoParticipante;
use App\Models\Fase;
use App\Models\Participante;
use App\Models\RegistroDeCambio;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Reparte en grupos a los participantes confirmados de la categoría y lo
 * guarda: grupos, sus miembros y la semilla del sorteo. Una fase con
 * resultados no se resortea salvo `forzar`, que anula lo jugado.
 */
final class SortearGrupos
{
    public function __construct(private readonly RepartidorDeGrupos $repartidor = new RepartidorDeGrupos) {}

    public function handle(Fase $fase, User $usuario, bool $forzar = false): RepartoDeGrupos
    {
        if (! $fase->tipo->esDeGrupos()) {
            throw new OperacionNoPermitida('Solo se sortean las fases de grupos.');
        }

        if ($fase->tieneResultados() && ! $forzar) {
            throw new FaseConResultados;
        }

        $participantes = $fase->categoria->participantes()
            ->where('estado', EstadoParticipante::Activo->value)
            ->whereHas('inscripcion', fn ($consulta) => $consulta->where('estado', EstadoInscripcion::Confirmada->value))
            ->with('participanteJugadores')
            ->orderBy('id')
            ->get()
            ->map(fn (Participante $p): ParticipanteDto => new ParticipanteDto($p->id, $p->nombre, $p->cabeza_de_serie, $p->idsDeJugadores()))
            ->all();
        $participantes = array_values($participantes);

        $configuracion = $fase->configuracionDeGrupos();

        return DB::transaction(function () use ($fase, $usuario, $participantes, $configuracion): RepartoDeGrupos {
            $antes = [];
            foreach ($fase->grupos()->with('participantes')->get() as $grupoAnterior) {
                $antes[$grupoAnterior->nombre] = $grupoAnterior->participantes->pluck('id')->all();
            }

            $reparto = $this->repartidor->repartir($participantes, $configuracion);

            $fase->partidos()->forceDelete();
            $fase->clasificaciones()->delete();
            $fase->grupos()->delete();

            foreach ($reparto->grupos as $grupoDto) {
                $grupo = $fase->grupos()->create(['nombre' => $grupoDto->nombre, 'orden' => $grupoDto->orden]);

                foreach ($grupoDto->participantes as $posicion => $participanteId) {
                    $grupo->participantes()->attach($participanteId, ['posicion_sorteo' => $posicion + 1]);
                }
            }

            $fase->semilla_sorteo = $reparto->semilla;
            $fase->configuracion = $reparto->semilla === null ? $configuracion->toArray() : $configuracion->conSemilla($reparto->semilla)->toArray();
            $fase->estado = EstadoFase::Preparada;
            $fase->cerrada_en = null;
            $fase->save();

            $despues = [];
            foreach ($reparto->grupos as $grupoDto) {
                $despues[$grupoDto->nombre] = $grupoDto->participantes;
            }

            RegistroDeCambio::anotar('sortear_grupos', $fase, $usuario, $antes === [] ? null : $antes, ['grupos' => $despues, 'semilla' => $reparto->semilla, 'avisos' => $reparto->avisos]);

            return $reparto;
        });
    }
}
