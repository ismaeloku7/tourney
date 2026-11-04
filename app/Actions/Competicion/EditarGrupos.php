<?php

namespace App\Actions\Competicion;

use App\Competicion\Errores\OperacionNoPermitida;
use App\Competicion\Errores\ParticipanteDuplicado;
use App\Competicion\GeneradorRoundRobin;
use App\Enums\EstadoFase;
use App\Enums\EstadoInscripcion;
use App\Enums\EstadoParticipante;
use App\Enums\EstadoPartido;
use App\Models\Fase;
use App\Models\Grupo;
use App\Models\Participante;
use App\Models\RegistroDeCambio;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Deja los grupos de una fase como los pone el organizador, antes o
 * después del sorteo. Solo se tocan los grupos que cambian: se rehacen
 * sus partidos (y se pierden sus resultados y horarios); los demás
 * quedan como estaban. Si la fase no tenía grupos, se crean todos.
 */
final class EditarGrupos
{
    public function __construct(
        private readonly GeneradorRoundRobin $generador = new GeneradorRoundRobin,
        private readonly RecalcularClasificacion $recalcular = new RecalcularClasificacion,
    ) {}

    /**
     * @param  list<list<int>>  $grupos  Ids de pareja por grupo (A, B…).
     * @return array{cambiados: list<string>, partidos: int, sinGrupo: list<string>}
     */
    public function handle(Fase $fase, array $grupos, User $usuario): array
    {
        if (! $fase->tipo->esDeGrupos()) {
            throw new OperacionNoPermitida('Solo se editan los grupos de una fase de grupos.');
        }

        if ($fase->estado === EstadoFase::Cerrada) {
            throw new OperacionNoPermitida('La fase está cerrada: ya no se pueden cambiar sus grupos.');
        }

        while ($grupos !== [] && end($grupos) === []) {
            array_pop($grupos);
        }

        if ($grupos === []) {
            throw new OperacionNoPermitida('No hay ninguna pareja en los grupos.');
        }

        $parejas = $fase->categoria->participantes()
            ->where('estado', EstadoParticipante::Activo->value)
            ->whereHas('inscripcion', fn ($q) => $q->where('estado', EstadoInscripcion::Confirmada->value))
            ->pluck('nombre', 'id');

        $vistos = [];
        foreach ($grupos as $miembros) {
            foreach ($miembros as $id) {
                if (! $parejas->has($id)) {
                    throw new OperacionNoPermitida("La pareja {$id} no está confirmada en esta categoría.");
                }
                if (isset($vistos[$id])) {
                    throw new ParticipanteDuplicado($id);
                }
                $vistos[$id] = true;
            }
        }

        $sinGrupo = array_values(array_map(fn (mixed $nombre): string => (string) $nombre, $parejas->except(array_keys($vistos))->all()));

        return DB::transaction(function () use ($fase, $grupos, $usuario, $sinGrupo): array {
            $configuracion = $fase->configuracionDeGrupos();
            $existentes = $fase->grupos()->with('participantes')->get()->keyBy('orden');
            $antes = [];
            $despues = [];
            $cambiados = [];
            $partidos = 0;

            foreach ($existentes as $grupo) {
                $antes[$grupo->nombre] = array_values(array_map('intval', $grupo->participantes->pluck('id')->all()));
            }

            foreach ($grupos as $i => $miembros) {
                $orden = $i + 1;
                $grupo = $existentes->get($orden);
                $actuales = $grupo !== null ? array_values(array_map('intval', $grupo->participantes->pluck('id')->all())) : null;

                if ($grupo !== null && $actuales !== null && self::mismos($actuales, $miembros)) {
                    $despues[$grupo->nombre] = $actuales;

                    continue;
                }

                $grupo ??= $fase->grupos()->create(['nombre' => Grupo::nombrePorOrden($orden), 'orden' => $orden]);

                $grupo->partidos()->forceDelete();
                $grupo->clasificaciones()->delete();
                $grupo->participantes()->sync(collect($miembros)->mapWithKeys(fn (int $id, int $k): array => [$id => ['posicion_sorteo' => $k + 1]])->all());
                $grupo->unsetRelation('participantes');

                foreach ($this->generador->generar($grupo->aDto(), $configuracion->partidosPorParticipante, $configuracion->idaYVuelta) as $dto) {
                    $fase->partidos()->create([
                        'grupo_id' => $grupo->id,
                        'jornada' => $dto->jornada,
                        'local_id' => $dto->local,
                        'visitante_id' => $dto->visitante,
                        'estado' => EstadoPartido::Pendiente,
                    ]);
                    $partidos++;
                }

                $this->recalcular->handle($grupo);
                $cambiados[] = $grupo->nombre;
                $despues[$grupo->nombre] = $miembros;
            }

            // Los grupos que sobran se quitan con sus partidos.
            foreach ($existentes as $orden => $grupo) {
                if ($orden > count($grupos)) {
                    $grupo->partidos()->forceDelete();
                    $grupo->clasificaciones()->delete();
                    $grupo->participantes()->detach();
                    $grupo->delete();
                    $cambiados[] = $grupo->nombre;
                }
            }

            if ($cambiados === []) {
                return ['cambiados' => [], 'partidos' => 0, 'sinGrupo' => $sinGrupo];
            }

            $fase->configuracion = $configuracion->conGruposManuales($grupos)->toArray();
            if ($fase->estado === EstadoFase::Pendiente) {
                $fase->estado = EstadoFase::Preparada;
            }
            $fase->save();

            RegistroDeCambio::anotar('editar_grupos', $fase, $usuario, $antes === [] ? null : $antes, ['grupos' => $despues, 'cambiados' => $cambiados]);

            return ['cambiados' => $cambiados, 'partidos' => $partidos, 'sinGrupo' => $sinGrupo];
        });
    }

    /**
     * @param  list<int>  $a
     * @param  list<int>  $b
     */
    private static function mismos(array $a, array $b): bool
    {
        sort($a);
        sort($b);

        return $a === $b;
    }
}
