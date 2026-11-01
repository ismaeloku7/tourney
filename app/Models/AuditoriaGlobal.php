<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Lo que la plataforma hace sobre un organizador. Una fila no se edita:
 * solo `created_at`.
 *
 * @property int $id
 * @property int|null $usuario_plataforma_id
 * @property string|null $organizador_id
 * @property string $accion
 * @property array<string, mixed>|null $detalles
 */
#[Fillable(['usuario_plataforma_id', 'organizador_id', 'accion', 'detalles'])]
class AuditoriaGlobal extends Model
{
    use CentralConnection;

    public const UPDATED_AT = null;

    protected $table = 'auditoria_global';

    protected function casts(): array
    {
        return [
            'detalles' => 'array',
        ];
    }

    /** @return BelongsTo<Organizador, $this> */
    public function organizador(): BelongsTo
    {
        return $this->belongsTo(Organizador::class);
    }

    /** @return BelongsTo<UsuarioPlataforma, $this> */
    public function usuarioPlataforma(): BelongsTo
    {
        return $this->belongsTo(UsuarioPlataforma::class);
    }

    /**
     * @param  array<string, mixed>|null  $detalles
     */
    public static function anotar(string $accion, ?Organizador $organizador = null, ?UsuarioPlataforma $quien = null, ?array $detalles = null): self
    {
        return self::query()->create([
            'usuario_plataforma_id' => $quien?->id,
            'organizador_id' => $organizador?->id,
            'accion' => $accion,
            'detalles' => $detalles,
        ]);
    }
}
