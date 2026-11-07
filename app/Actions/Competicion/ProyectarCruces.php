<?php

namespace App\Actions\Competicion;

use App\Competicion\Datos\Clasificacion as ClasificacionDto;
use App\Competicion\Datos\Clasificado;
use App\Competicion\Datos\ConfiguracionDeEliminatoria;
use App\Competicion\Datos\CruceProyectado;
use App\Competicion\Datos\Cuadro;
use App\Competicion\Datos\LineaDeClasificacion;
use App\Competicion\Datos\Partido as PartidoDto;
use App\Competicion\Datos\Puntuacion;
use App\Competicion\Datos\ReglaDeClasificados;
use App\Competicion\ProyectorDeCruces;
use App\Enums\CriterioDeDesempate;
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
        $datos = $this->datos($fase);

        return $datos === null ? [] : $this->proyector->proyectar(...$datos);
    }

    /**
     * El cuadro entero que saldría si la fase de grupos acabase así, con
     * sus clasificados por id de participante. Null en los mismos casos en
     * que no hay cruces previstos.
     *
     * @return array{0: Cuadro, 1: array<int, Clasificado>}|null
     */
    public function cuadro(Fase $fase): ?array
    {
        $datos = $this->datos($fase);

        return $datos === null ? null : $this->proyector->cuadroPrevisto(...$datos);
    }

    /**
     * Lo que necesita el proyector, o null si no hay nada que proyectar.
     *
     * @return array{0: list<ClasificacionDto>, 1: ReglaDeClasificados, 2: list<CriterioDeDesempate>, 3: Puntuacion, 4: list<PartidoDto>, 5: array<int, string>, 6: ConfiguracionDeEliminatoria}|null
     */
    private function datos(Fase $fase): ?array
    {
        if (! $fase->tipo->esDeGrupos() || $fase->estado === EstadoFase::Cerrada) {
            return null;
        }

        $siguiente = $fase->siguiente();

        if ($siguiente === null || $siguiente->tipo !== TipoDeFase::Eliminatoria) {
            return null;
        }

        $grupos = $fase->grupos()->with(['clasificaciones', 'partidos'])->get();
        $clasificaciones = [];

        foreach ($grupos as $grupo) {
            if ($grupo->clasificaciones->isEmpty()) {
                return null;
            }

            $clasificaciones[] = $this->aDto($grupo);
        }

        $configuracion = $fase->configuracionDeGrupos();
        $partidos = array_values($fase->partidos()->with('sets')->get()->map(fn (Partido $p): PartidoDto => $p->aDto())->all());

        return [
            $clasificaciones,
            $configuracion->clasificados,
            $configuracion->criteriosDeDesempate,
            $configuracion->puntuacion,
            $partidos,
            $grupos->pluck('nombre', 'id')->map(fn (mixed $n): string => (string) $n)->all(),
            $siguiente->configuracionDeEliminatoria(),
        ];
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
