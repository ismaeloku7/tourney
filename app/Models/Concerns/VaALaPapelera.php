<?php

namespace App\Models\Concerns;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * Borrado lógico: `delete()` manda el registro a la papelera y
 * `restore()` lo devuelve; `forceDelete()` lo borra del todo (y entonces
 * mandan las claves foráneas de la base).
 *
 * Al mandarlo a la papelera se aplica a lo que cuelga de él lo que dice
 * `ReglasDePapelera`, y al restaurarlo vuelve lo que se fue con él. Las
 * consultas normales no ven lo que está en la papelera; para verlo,
 * `withTrashed()` u `onlyTrashed()`.
 *
 * Solo pasa con `delete()` sobre el modelo: un `->delete()` sobre una
 * consulta no dispara eventos y no arrastra nada.
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait VaALaPapelera
{
    use SoftDeletes;

    /** Lo que se fue a la papelera con él vuelve si se borró en este margen. */
    private const SEGUNDOS_DE_MARGEN = 120;

    private ?CarbonInterface $borradoEn = null;

    public static function bootVaALaPapelera(): void
    {
        static::deleted(function (self $modelo): void {
            if (! $modelo->isForceDeleting()) {
                $modelo->arrastrarALaPapelera();
            }
        });

        static::restoring(function (self $modelo): void {
            $valor = $modelo->getAttribute($modelo->getDeletedAtColumn());
            $modelo->borradoEn = $valor instanceof CarbonInterface ? $valor : null;
        });

        static::restored(function (self $modelo): void {
            $modelo->devolverDeLaPapelera();
        });
    }

    private function arrastrarALaPapelera(): void
    {
        foreach (ReglasDePapelera::VINCULOS[$this->getTable()] ?? [] as [$tabla, $columna, $regla]) {
            if ($regla === ReglasDePapelera::PAPELERA) {
                $this->mandarHijos($tabla, $columna);
            } elseif ($regla === ReglasDePapelera::BORRAR) {
                DB::table($tabla)->where($columna, $this->getKey())->delete();
            } elseif ($regla === ReglasDePapelera::VACIAR) {
                DB::table($tabla)->where($columna, $this->getKey())->update([$columna => null]);
            }
        }
    }

    private function devolverDeLaPapelera(): void
    {
        if ($this->borradoEn === null) {
            return;
        }

        foreach (ReglasDePapelera::VINCULOS[$this->getTable()] ?? [] as [$tabla, $columna, $regla]) {
            if ($regla !== ReglasDePapelera::PAPELERA) {
                continue;
            }

            $this->devolverHijos($tabla, $columna, $this->borradoEn);
        }

        $this->borradoEn = null;
    }

    private function mandarHijos(string $tabla, string $columna): void
    {
        $modelo = ReglasDePapelera::MODELOS[$tabla];

        foreach ($modelo::query()->where($columna, $this->getKey())->get() as $hijo) {
            $hijo->delete();
        }
    }

    private function devolverHijos(string $tabla, string $columna, CarbonInterface $borradoEn): void
    {
        $modelo = ReglasDePapelera::MODELOS[$tabla];

        $hijos = $modelo::onlyTrashed()
            ->where($columna, $this->getKey())
            ->whereBetween('deleted_at', [$borradoEn, $borradoEn->copy()->addSeconds(self::SEGUNDOS_DE_MARGEN)])
            ->get();

        foreach ($hijos as $hijo) {
            $hijo->restore();
        }
    }
}
