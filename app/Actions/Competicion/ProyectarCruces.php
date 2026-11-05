<?php

namespace App\Actions\Competicion;

use App\Competicion\Datos\Clasificacion as ClasificacionDto;
use App\Competicion\Datos\CruceProyectado;
use App\Competicion\Datos\LineaDeClasificacion;
use App\Competicion\Datos\Partido as PartidoDto;
use App\Competicion\ProyectorDeCruces;
use App\Enums\EstadoFase;
use App\Enums\TipoDeFase;
use App\Models\Clasificacion;
use App\Models\Fase;
use App\Models\Grupo;
use App\Models\Partido;

/**
 * Los cruces previstos de una fase de grupos aún abierta cuya fase
 * siguiente es un cuadro: lee la clasificación materializada y se la pasa
 * al proyector. Vacío si la fase está cerrada (el cuadro ya es real), si
 * no hay cuadro detrás o si los cruces se deciden por sorteo.
 */
final class ProyectarCruces
{
    public function __construct(private readonly ProyectorDeCruces $proyector = new ProyectorDeCruces) {}

    /** @return array<int, CruceProyectado> Id de participante → su cruce previsto. */
    public function handle(Fase $fase): array
    {
        if (! $fase->tipo->esDeGrupos() || $fase->estado === EstadoFase::Cerrada) {
            return [];
        }

        $siguiente = $fase->siguiente();

        if ($siguiente === null || $siguiente->tipo !== TipoDeFase::Eliminatoria) {
            return [];
        }

        $grupos = $fase->grupos()->with(['clasificaciones', 'partidos'])->get();
        $clasificaciones = [];

        foreach ($grupos as $grupo) {
            if ($grupo->clasificaciones->isEmpty()) {
                return [];
            }

            $clasificaciones[] = $this->aDto($grupo);
        }

        $configuracion = $fase->configuracionDeGrupos();
        $partidos = array_values($fase->partidos()->with('sets')->get()->map(fn (Partido $p): PartidoDto => $p->aDto())->all());

        return $this->proyector->proyectar(
            $clasificaciones,
            $configuracion->clasificados,
            $configuracion->criteriosDeDesempate,
            $configuracion->puntuacion,
            $partidos,
            $grupos->pluck('nombre', 'id')->all(),
            $siguiente->configuracionDeEliminatoria(),
        );
    }

    private function aDto(Grupo $grupo): ClasificacionDto
    {
        $lineas = array_values($grupo->clasificaciones->map(fn (Clasificacion $l): LineaDeClasificacion => new LineaDeClasificacion(
            participante: $l->participante_id,
            posicion: $l->posicion,
            puntos: $l->puntos,
            pj: $l->pj,
            pg: $l->pg,
            pp: $l->pp,
            setsFavor: $l->sets_favor,
            setsContra: $l->sets_contra,
            juegosFavor: $l->juegos_favor,
            juegosContra: $l->juegos_contra,
            criterioAplicado: $l->desempate_aplicado,
            sorteo: $l->sorteo_desempate,
        ))->all());

        return new ClasificacionDto($grupo->id, $lineas, $grupo->partidos->every(fn (Partido $p): bool => $p->cerrado()));
    }
}
