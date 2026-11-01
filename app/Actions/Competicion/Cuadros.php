<?php

namespace App\Actions\Competicion;

use App\Competicion\Datos\Cuadro;
use App\Competicion\Datos\Origen;
use App\Competicion\Datos\Partido as PartidoDto;
use App\Enums\EstadoPartido;
use App\Enums\TipoDeFase;
use App\Models\Fase;
use App\Models\Partido;

/**
 * Va y viene entre el cuadro del motor y las filas de `partidos`: crea
 * un cuadro recién construido (reescribiendo los ids provisionales de los
 * orígenes por los reales), carga el de una fase con sus consolaciones y
 * guarda los cambios que el avance o la reversión han hecho.
 */
final class Cuadros
{
    /**
     * Crea en `$fase` los partidos de un cuadro con ids provisionales.
     * Devuelve el mapa id provisional → id real.
     *
     * @param  array<int, int>  $formatosPorRonda
     * @return array<int, int>
     */
    public function crear(Fase $fase, Cuadro $cuadro, array $formatosPorRonda = []): array
    {
        $mapa = [];
        $filas = [];

        $ordenados = $cuadro->partidos;
        usort($ordenados, fn (PartidoDto $a, PartidoDto $b): int => [$a->ronda, $a->esTercerPuesto ? 1 : 0, $a->posicion] <=> [$b->ronda, $b->esTercerPuesto ? 1 : 0, $b->posicion]);

        foreach ($ordenados as $dto) {
            $fila = $fase->partidos()->create([
                'ronda' => $dto->ronda,
                'posicion' => $dto->posicion,
                'es_tercer_puesto' => $dto->esTercerPuesto,
                'local_id' => $dto->local,
                'visitante_id' => $dto->visitante,
                'estado' => $dto->estado,
                'ganador_id' => $dto->ganador,
                'formato_partido_id' => $formatosPorRonda[$dto->ronda] ?? null,
            ]);
            $mapa[(int) $dto->id] = $fila->id;
            $filas[] = [$fila, $dto];
        }

        foreach ($filas as [$fila, $dto]) {
            $fila->origen_local = $this->reescribir($dto->origenLocal, $mapa);
            $fila->origen_visitante = $this->reescribir($dto->origenVisitante, $mapa);
            $fila->save();
        }

        return $mapa;
    }

    /**
     * El cuadro de la fase con los partidos de sus consolaciones: los
     * orígenes de la consolación apuntan a partidos de esta fase.
     */
    public function cargar(Fase $fase): Cuadro
    {
        $partidos = $fase->partidos()->with('sets')->get();
        $tamano = 2 * $partidos->where('ronda', 1)->where('es_tercer_puesto', false)->count();
        $rondas = (int) ($partidos->max('ronda') ?? 0);

        foreach ($fase->fasesDerivadas()->where('tipo', TipoDeFase::Consolacion->value)->get() as $consolacion) {
            $partidos = $partidos->merge($consolacion->partidos()->with('sets')->get());
        }

        return new Cuadro(max($tamano, 2), max($rondas, 1), $partidos->map(fn (Partido $p): PartidoDto => $p->aDto())->values()->all());
    }

    /**
     * Guarda en la base lo que haya cambiado del cuadro: participantes de
     * los huecos, estado y ganador.
     */
    public function guardar(Cuadro $cuadro): void
    {
        foreach ($cuadro->partidos as $dto) {
            $fila = Partido::query()->find($dto->id);

            if ($fila === null) {
                continue;
            }

            $cambios = [];
            if ($fila->local_id !== $dto->local) {
                $cambios['local_id'] = $dto->local;
            }
            if ($fila->visitante_id !== $dto->visitante) {
                $cambios['visitante_id'] = $dto->visitante;
            }
            if ($fila->ganador_id !== $dto->ganador) {
                $cambios['ganador_id'] = $dto->ganador;
            }
            if ($fila->estado !== $dto->estado) {
                $cambios['estado'] = $dto->estado;
            }

            if ($cambios !== []) {
                // Un partido que vuelve a pendiente tras revertir conserva su franja si la tenía.
                if (($cambios['estado'] ?? null) === EstadoPartido::Pendiente && $fila->franja_id !== null) {
                    $cambios['estado'] = EstadoPartido::Programado;
                }

                $fila->fill($cambios)->save();
            }
        }
    }

    /**
     * @param  array<int, int>  $mapa
     * @return array{tipo: string, referencia: int, posicion: int|null}|null
     */
    private function reescribir(?Origen $origen, array $mapa): ?array
    {
        if ($origen === null) {
            return null;
        }

        if ($origen->apuntaA($origen->referencia) && isset($mapa[$origen->referencia])) {
            $origen = new Origen($origen->tipo, $mapa[$origen->referencia], $origen->posicion);
        }

        return $origen->toArray();
    }
}
