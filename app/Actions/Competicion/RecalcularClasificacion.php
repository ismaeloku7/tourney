<?php

namespace App\Actions\Competicion;

use App\Competicion\CalculadorDeClasificacion;
use App\Competicion\Datos\Clasificacion as ClasificacionDto;
use App\Competicion\Datos\LineaDeClasificacion;
use App\Competicion\Datos\Partido as PartidoDto;
use App\Models\Clasificacion;
use App\Models\Grupo;
use App\Models\Partido;

/**
 * Materializa la clasificación de un grupo en `clasificaciones`,
 * conservando los sorteos de desempate ya hechos para que no cambien
 * entre recálculos. Mientras no se ha jugado nada en el grupo, el orden es
 * el de partida: el cabeza de serie primero y luego por nivel medio, de
 * más a menos (sin sorteos de desempate).
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

        $sinJugar = ! collect($partidos)->contains(fn (PartidoDto $p): bool => $p->cerrado());
        $clasificacion = $this->calculador->calcular($participantes, $partidos, $configuracion->puntuacion, $configuracion->criteriosDeDesempate, $sinJugar ? [] : $sorteos, $grupo->id);

        if ($sinJugar) {
            $clasificacion = $this->porSiembra($grupo, $clasificacion);
        }

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

    /**
     * Sin nada jugado: el cabeza de serie (el de número más bajo) primero,
     * luego de más a menos nivel medio, los que no tienen nivel al final.
     */
    private function porSiembra(Grupo $grupo, ClasificacionDto $clasificacion): ClasificacionDto
    {
        $parejas = $grupo->participantes()->with('jugadores')->get()->keyBy('id');
        $clave = function (LineaDeClasificacion $linea) use ($parejas): array {
            $pareja = $parejas->get($linea->participante);
            $nivel = $pareja?->nivelMedio();

            return [$pareja?->cabeza_de_serie ?? PHP_INT_MAX, $nivel === null ? 1 : 0, -($nivel ?? 0), (string) $pareja?->nombre, $linea->participante];
        };

        $lineas = $clasificacion->lineas;
        usort($lineas, fn (LineaDeClasificacion $a, LineaDeClasificacion $b): int => $clave($a) <=> $clave($b));

        return new ClasificacionDto(
            $clasificacion->grupo,
            array_values(array_map(fn (LineaDeClasificacion $l, int $i): LineaDeClasificacion => $l->con(posicion: $i + 1, criterioAplicado: null, sorteo: null), $lineas, array_keys($lineas))),
            $clasificacion->completa,
        );
    }
}
