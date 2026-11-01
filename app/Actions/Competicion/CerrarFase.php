<?php

namespace App\Actions\Competicion;

use App\Competicion\ConstructorDeConsolacion;
use App\Competicion\ConstructorDeCuadro;
use App\Competicion\Datos\Clasificacion as ClasificacionDto;
use App\Competicion\Datos\Clasificado;
use App\Competicion\Datos\Partido as PartidoDto;
use App\Competicion\Errores\OperacionNoPermitida;
use App\Competicion\SelectorDeClasificados;
use App\Enums\EstadoFase;
use App\Enums\OrigenDeConsolacion;
use App\Enums\TipoDeFase;
use App\Models\Clasificacion;
use App\Models\Fase;
use App\Models\Grupo;
use App\Models\Partido;
use App\Models\RegistroDeCambio;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Avisos\Avisos;

/**
 * Cierra una fase con todos sus partidos jugados. En una fase de grupos,
 * decide los clasificados, los anota en la clasificación y, si la fase
 * siguiente es un cuadro, lo construye (y la consolación, si la hay) con
 * sus partidos. Devuelve los clasificados.
 */
final class CerrarFase
{
    public function __construct(
        private readonly RecalcularClasificacion $recalcular = new RecalcularClasificacion,
        private readonly SelectorDeClasificados $selector = new SelectorDeClasificados,
        private readonly ConstructorDeCuadro $constructor = new ConstructorDeCuadro,
        private readonly ConstructorDeConsolacion $consolacion = new ConstructorDeConsolacion,
        private readonly Cuadros $cuadros = new Cuadros,
    ) {}

    /**
     * @return list<Clasificado>
     */
    public function handle(Fase $fase, User $usuario): array
    {
        if ($fase->estado === EstadoFase::Cerrada) {
            throw new OperacionNoPermitida('La fase ya está cerrada.');
        }

        if ($fase->partidos()->doesntExist()) {
            throw new OperacionNoPermitida('La fase no tiene partidos.');
        }

        if ($fase->partidos()->whereNotIn('estado', ['jugado', 'walkover'])->exists()) {
            throw new OperacionNoPermitida('Quedan partidos sin resultado: no se puede cerrar la fase.');
        }

        $resultado = DB::transaction(function () use ($fase, $usuario): array {
            $clasificados = $fase->tipo->esDeGrupos() ? $this->cerrarGrupos($fase) : [];

            $fase->estado = EstadoFase::Cerrada;
            $fase->cerrada_en = now();
            $fase->save();

            RegistroDeCambio::anotar('cerrar_fase', $fase, $usuario, null, [
                'clasificados' => array_map(fn (Clasificado $c): array => ['participante' => $c->participante, 'origen' => $c->origen, 'orden' => $c->orden], $clasificados),
            ]);

            return $clasificados;
        });

        if ($fase->tipo->esDeGrupos()) {
            app(Avisos::class)->crucesDe($fase);
        }

        return $resultado;
    }

    /**
     * @return list<Clasificado>
     */
    private function cerrarGrupos(Fase $fase): array
    {
        $configuracion = $fase->configuracionDeGrupos();
        $grupos = $fase->grupos()->with('participantes')->get();
        $nombres = $grupos->pluck('nombre', 'id')->all();
        $partidos = array_values($fase->partidos()->with('sets')->get()->map(fn (Partido $p): PartidoDto => $p->aDto())->all());

        $clasificaciones = array_values($grupos->map(fn (Grupo $g): ClasificacionDto => $this->recalcular->handle($g))->all());

        $clasificados = $this->selector->seleccionar($clasificaciones, $configuracion->clasificados, $configuracion->criteriosDeDesempate, $configuracion->puntuacion, $partidos, $nombres);

        Clasificacion::query()->where('fase_id', $fase->id)->update(['clasificado_como' => null]);
        foreach ($clasificados as $clasificado) {
            Clasificacion::query()->where('fase_id', $fase->id)->where('participante_id', $clasificado->participante)->update(['clasificado_como' => $clasificado->origen]);
        }

        $siguiente = $fase->siguiente();

        if ($siguiente !== null && $siguiente->tipo === TipoDeFase::Eliminatoria) {
            $this->construirCuadro($fase, $siguiente, $clasificados, $clasificaciones, $nombres);
        }

        return $clasificados;
    }

    /**
     * @param  list<Clasificado>  $clasificados
     * @param  list<ClasificacionDto>  $clasificaciones
     * @param  array<int, string>  $nombres
     */
    private function construirCuadro(Fase $grupos, Fase $cuadroFase, array $clasificados, array $clasificaciones, array $nombres): void
    {
        $configuracion = $cuadroFase->configuracionDeEliminatoria();
        $cuadro = $this->constructor->construir($clasificados, $configuracion);

        $cuadroFase->partidos()->forceDelete();
        $this->cuadros->crear($cuadroFase, $cuadro, $configuracion->formatosPorRonda);
        $cuadroFase->fill(['estado' => EstadoFase::Preparada, 'fase_origen_id' => $grupos->id, 'semilla_sorteo' => $configuracion->semilla])->save();

        $consolacionFase = Fase::query()->where('categoria_id', $cuadroFase->categoria_id)->where('tipo', TipoDeFase::Consolacion->value)->where('orden', '>', $cuadroFase->orden)->orderBy('orden')->first();

        if ($consolacionFase === null || $configuracion->consolacion === OrigenDeConsolacion::Ninguna) {
            return;
        }

        $consolacionFase->partidos()->forceDelete();
        $configuracionConsolacion = $consolacionFase->configuracionDeEliminatoria();

        if ($configuracion->consolacion === OrigenDeConsolacion::PerdedoresPrimeraRonda) {
            $principal = $this->cuadros->cargar($cuadroFase);
            $this->cuadros->crear($consolacionFase, $this->consolacion->desdePerdedores($principal, $configuracionConsolacion));
            $consolacionFase->fill(['estado' => EstadoFase::Preparada, 'fase_origen_id' => $cuadroFase->id])->save();

            return;
        }

        $noClasificados = $this->noClasificados($clasificados, $clasificaciones, $nombres);

        if (count($noClasificados) >= 2) {
            $this->cuadros->crear($consolacionFase, $this->consolacion->construir($noClasificados, $configuracionConsolacion));
            $consolacionFase->fill(['estado' => EstadoFase::Preparada, 'fase_origen_id' => $grupos->id])->save();
        }
    }

    /**
     * Los que no pasan, por posición en su grupo y después por grupo.
     *
     * @param  list<Clasificado>  $clasificados
     * @param  list<ClasificacionDto>  $clasificaciones
     * @param  array<int, string>  $nombres
     * @return list<Clasificado>
     */
    private function noClasificados(array $clasificados, array $clasificaciones, array $nombres): array
    {
        $pasan = array_map(fn (Clasificado $c): int => $c->participante, $clasificados);
        $candidatos = [];

        foreach ($clasificaciones as $clasificacion) {
            foreach ($clasificacion->lineas as $linea) {
                if (! in_array($linea->participante, $pasan, true)) {
                    $candidatos[] = [$linea, $clasificacion->grupo];
                }
            }
        }

        usort($candidatos, fn (array $a, array $b): int => [$a[0]->posicion, $b[0]->puntos, $b[0]->diferenciaDeSets(), $a[0]->participante] <=> [$b[0]->posicion, $a[0]->puntos, $a[0]->diferenciaDeSets(), $b[0]->participante]);

        $orden = 1;

        return array_map(fn (array $c): Clasificado => new Clasificado($c[0]->participante, $c[1], $c[0]->posicion, $c[0]->posicion.'º '.($nombres[$c[1]] ?? $c[1]), $orden++), $candidatos);
    }
}
