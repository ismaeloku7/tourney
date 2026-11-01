<?php

namespace App\Models;

use App\Enums\EstadoInscripcion;
use App\Enums\OrigenInscripcion;
use App\Models\Concerns\VaALaPapelera;
use App\Tenancy\EnLaBaseDelOrganizador;
use Database\Factories\InscripcionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * La inscripción de un participante en una categoría: pendiente,
 * confirmada, en lista de espera o baja. El cupo se cuenta sobre las
 * confirmadas.
 *
 * @property int $id
 * @property int $categoria_id
 * @property int $participante_id
 * @property EstadoInscripcion $estado
 * @property int|null $posicion_espera
 * @property Carbon|null $pago_marcado_en
 * @property string|null $pago_notas
 * @property string|null $notas
 * @property OrigenInscripcion $origen
 * @property array<string, mixed>|null $respuestas_extra
 * @property Carbon|null $confirmada_en
 * @property Carbon|null $baja_en
 * @property int|null $registrada_por
 */
#[Fillable(['categoria_id', 'participante_id', 'estado', 'posicion_espera', 'pago_marcado_en', 'pago_notas', 'notas', 'origen', 'respuestas_extra', 'confirmada_en', 'baja_en', 'registrada_por'])]
class Inscripcion extends Model
{
    /** @use HasFactory<InscripcionFactory> */
    use EnLaBaseDelOrganizador, HasFactory, VaALaPapelera;

    protected $table = 'inscripciones';

    protected function casts(): array
    {
        return [
            'estado' => EstadoInscripcion::class,
            'posicion_espera' => 'integer',
            'pago_marcado_en' => 'datetime',
            'origen' => OrigenInscripcion::class,
            'respuestas_extra' => 'array',
            'confirmada_en' => 'datetime',
            'baja_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<Categoria, $this> */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    /** @return BelongsTo<Participante, $this> */
    public function participante(): BelongsTo
    {
        return $this->belongsTo(Participante::class);
    }

    /** @return BelongsTo<User, $this> */
    public function registradaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrada_por');
    }
}
