<?php

namespace App\Actions\Competicion;

use App\Competicion\CalculadorDeClasificacion;
use App\Competicion\Datos\Clasificacion as ClasificacionDto;
use App\Competicion\Datos\Partido as PartidoDto;
use App\Models\Clasificacion;
use App\Models\Grupo;
use App\Models\Partido;

/**
 * Materializa la clasificación de un grupo en `clasificaciones`,
 * conservando los sorteos de desempate ya hechos para que no cambien
 * entre recálculos.
 */
final class RecalcularClasificacion
{
    public function __construct(private readonly CalculadorDeClasificacion $calculador = new CalculadorDeClasificacion) {}

    public function handle(Grupo $grupo): ClasificacionDto
    {
        $fase = $grupo->fase;
        $configuracion = $fase->configuracionDeGrupos();
        $participantes = array_values($grupo->participantes()->pluck('participantes.id')->map(fn (mixed $id): int => (int) $id)->all());
        $partidos = array_values($grupo->partidos()->with('sets')->get()->map(fn (Partido $p): PartidoDto => $p->aDto())->all());
        $sorteos = Clasificacion::query()->where('grupo_id', $grupo->id)->whereNotNull('sorteo_desempate')->pluck('sorteo_desempate', 'participante_id')->map(fn (mixed $v): int => (int) $v)->all();

        $clasificacion = $this->calculador->calcular($participantes, $partidos, $configuracion->puntuacion, $configuracion->criteriosDeDesempate, $sorteos, $grupo->id);

        foreach ($clasificacion->lineas as $linea) {
            Clasificacion::query()->updateOrCreate(
                ['fase_id' => $fase->id, 'grupo_id' => $grupo->id, 'participante_id' => $linea->participante],
                [
                    'posicion' => $linea->posicion,
                    'puntos' => $linea->puntos,
                    'pj' => $linea->pj,
                    'pg' => $linea->pg,
                    'pp' => $linea->pp,
                    'sets_favor' => $linea->setsFavor,
                    'sets_contra' => $linea->setsContra,
                    'juegos_favor' => $linea->juegosFavor,
                    'juegos_contra' => $linea->juegosContra,
                    'desempate_aplicado' => $linea->criterioAplicado,
                    'sorteo_desempate' => $linea->sorteo,
                    'calculada_en' => now(),
                ],
            );
        }

        return $clasificacion;
    }
}
