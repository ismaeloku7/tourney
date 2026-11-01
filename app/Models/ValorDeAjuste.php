<?php

namespace App\Models;

use App\Enums\Ajuste;
use App\Tenancy\EnLaBaseDelOrganizador;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Una fila de `ajustes`: la clave del enum Ajuste y su valor. Se lee con
 * `ValorDeAjuste::obtener(Ajuste::X)` y se guarda con `fijar()`.
 *
 * @property string $clave
 * @property mixed $valor
 */
#[Fillable(['clave', 'valor'])]
class ValorDeAjuste extends Model
{
    use EnLaBaseDelOrganizador;

    public $timestamps = false;

    public $incrementing = false;

    protected $table = 'ajustes';

    protected $primaryKey = 'clave';

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['valor' => 'json'];
    }

    /** El valor guardado, o el valor por defecto si no se ha tocado. */
    public static function obtener(Ajuste $ajuste): bool|int|string|null
    {
        $fila = self::query()->find($ajuste->value);

        if ($fila === null) {
            return $ajuste->porDefecto();
        }

        $valor = $fila->valor;

        return is_bool($valor) || is_int($valor) || is_string($valor) ? $valor : $ajuste->porDefecto();
    }

    /**
     * @param  array<string, bool|int|string|null>  $valores  Clave de `Ajuste` => valor.
     */
    public static function fijar(array $valores): void
    {
        foreach ($valores as $clave => $valor) {
            Ajuste::from($clave);

            self::query()->updateOrCreate(['clave' => $clave], ['valor' => $valor]);
            self::query()->whereKey($clave)->update(['updated_at' => now()]);
        }
    }
}
