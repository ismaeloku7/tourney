<?php

namespace App\Models;

use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Un plan de la plataforma con sus límites. Un límite a nulo es
 * ilimitado. Los límites se comprueban al publicar un torneo y al
 * confirmar una inscripción, contando en la base del organizador.
 *
 * @property int $id
 * @property string $clave
 * @property string $nombre
 * @property string|null $precio_mensual
 * @property int|null $torneos_activos
 * @property int|null $parejas_por_torneo
 * @property int|null $categorias_por_torneo
 * @property bool $sin_marca
 * @property bool $push_ilimitado
 * @property bool $exportaciones
 * @property bool $dominio_propio
 * @property bool $varios_administradores
 * @property int $orden
 * @property bool $activo
 */
#[Fillable(['clave', 'nombre', 'precio_mensual', 'torneos_activos', 'parejas_por_torneo', 'categorias_por_torneo', 'sin_marca', 'push_ilimitado', 'exportaciones', 'dominio_propio', 'varios_administradores', 'orden', 'activo'])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use CentralConnection, HasFactory;

    public const GRATIS = 'gratis';

    public const CLUB = 'club';

    public const ORGANIZADOR = 'organizador';

    protected $table = 'planes';

    protected function casts(): array
    {
        return [
            'precio_mensual' => 'decimal:2',
            'sin_marca' => 'boolean',
            'push_ilimitado' => 'boolean',
            'exportaciones' => 'boolean',
            'dominio_propio' => 'boolean',
            'varios_administradores' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    /** @return HasMany<Organizador, $this> */
    public function organizadores(): HasMany
    {
        return $this->hasMany(Organizador::class);
    }

    public static function porClave(string $clave): self
    {
        return self::query()->where('clave', $clave)->firstOrFail();
    }
}
