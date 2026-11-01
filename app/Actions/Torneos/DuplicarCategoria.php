<?php

namespace App\Actions\Torneos;

use App\Enums\EstadoFase;
use App\Models\Categoria;
use App\Models\Fase;
use App\Models\RegistroDeCambio;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Una categoría nueva en el mismo torneo, copiada de otra: sus datos
 * (cupo, precio, formato…) y, si se pide, sus fases con su configuración.
 * Nada de lo que es de la categoría original: ni parejas ni inscripciones,
 * ni grupos, partidos, sorteos o cruces. Las fases nacen pendientes.
 */
final class DuplicarCategoria
{
    /**
     * @param  array{nombre?: string, genero?: mixed, nivel?: string|null}  $cambios
     */
    public function handle(Categoria $origen, array $cambios = [], bool $conFases = true, ?User $usuario = null): Categoria
    {
        return DB::transaction(function () use ($origen, $cambios, $conFases, $usuario): Categoria {
            $nueva = $origen->replicate(['fases_count', 'confirmadas_count', 'inscripciones_count', 'participantes_count']);
            $nueva->fill([
                'nombre' => $cambios['nombre'] ?? $origen->nombre.' (copia)',
                'genero' => $cambios['genero'] ?? $origen->genero,
                'nivel' => array_key_exists('nivel', $cambios) ? $cambios['nivel'] : $origen->nivel,
                'orden' => (int) Categoria::query()->where('torneo_id', $origen->torneo_id)->max('orden') + 1,
            ]);
            $nueva->save();

            if ($conFases) {
                $this->copiarFases($origen, $nueva);
            }

            RegistroDeCambio::anotar('duplicar_categoria', $nueva, $usuario, ['origen' => $origen->id], [
                'nombre' => $nueva->nombre,
                'fases' => $nueva->fases()->count(),
            ]);

            return $nueva;
        });
    }

    /** Las fases en su orden, con la fase de origen de cada una apuntando a su copia. */
    private function copiarFases(Categoria $origen, Categoria $nueva): void
    {
        $copias = [];

        foreach ($origen->fases()->orderBy('orden')->get() as $fase) {
            $copia = Fase::query()->create([
                'categoria_id' => $nueva->id,
                'tipo' => $fase->tipo,
                'nombre' => $fase->nombre,
                'orden' => $fase->orden,
                'configuracion' => self::configuracionLimpia($fase->configuracion),
                'formato_partido_id' => $fase->formato_partido_id,
                'estado' => EstadoFase::Pendiente,
                'fase_origen_id' => null,
                'semilla_sorteo' => null,
                'cerrada_en' => null,
            ]);

            $copias[$fase->id] = $copia;
        }

        foreach ($origen->fases()->whereNotNull('fase_origen_id')->get() as $fase) {
            if (isset($copias[$fase->id], $copias[(int) $fase->fase_origen_id])) {
                $copias[$fase->id]->update(['fase_origen_id' => $copias[(int) $fase->fase_origen_id]->id]);
            }
        }
    }

    /**
     * La configuración sin lo que pertenece al sorteo de la original: la
     * semilla y los grupos o cruces hechos a mano (son parejas de la otra
     * categoría).
     *
     * @param  array<string, mixed>  $configuracion
     * @return array<string, mixed>
     */
    public static function configuracionLimpia(array $configuracion): array
    {
        foreach (['semilla', 'grupos_manuales', 'cruces_manuales'] as $clave) {
            if (array_key_exists($clave, $configuracion)) {
                $configuracion[$clave] = null;
            }
        }

        return $configuracion;
    }
}
