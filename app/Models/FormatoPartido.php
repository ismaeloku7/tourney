<?php

namespace App\Models;

use App\Models\Concerns\VaALaPapelera;
use App\Tenancy\EnLaBaseDelOrganizador;
use Database\Factories\FormatoPartidoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Una plantilla de formato de partido («Al mejor de 3 sets a 6»): sets,
 * juegos, tie-break, super tie-break, por tiempo y duración estimada.
 * Las reglas de pádel entran al motor por aquí.
 *
 * @property int $id
 * @property string $nombre
 * @property int $sets
 * @property int $juegos_por_set
 * @property int $diferencia_para_ganar_set
 * @property int|null $tie_break_en
 * @property int $tie_break_puntos
 * @property bool $super_tie_break_final
 * @property int $super_tie_break_puntos
 * @property bool $punto_de_oro
 * @property int|null $por_tiempo_minutos
 * @property int $duracion_estimada
 * @property bool $es_predeterminado
 */
#[Fillable(['nombre', 'sets', 'juegos_por_set', 'diferencia_para_ganar_set', 'tie_break_en', 'tie_break_puntos', 'super_tie_break_final', 'super_tie_break_puntos', 'punto_de_oro', 'por_tiempo_minutos', 'duracion_estimada', 'es_predeterminado'])]
class FormatoPartido extends Model
{
    /** @use HasFactory<FormatoPartidoFactory> */
    use EnLaBaseDelOrganizador, HasFactory, VaALaPapelera;

    protected $table = 'formatos_partido';

    protected function casts(): array
    {
        return [
            'sets' => 'integer',
            'juegos_por_set' => 'integer',
            'diferencia_para_ganar_set' => 'integer',
            'tie_break_en' => 'integer',
            'tie_break_puntos' => 'integer',
            'super_tie_break_final' => 'boolean',
            'super_tie_break_puntos' => 'integer',
            'punto_de_oro' => 'boolean',
            'por_tiempo_minutos' => 'integer',
            'duracion_estimada' => 'integer',
            'es_predeterminado' => 'boolean',
        ];
    }

    /**
     * Los cuatro formatos con los que nace cada organizador.
     *
     * @return list<array<string, mixed>>
     */
    public static function porDefecto(): array
    {
        return [
            ['nombre' => 'Al mejor de 3 sets a 6', 'sets' => 3, 'juegos_por_set' => 6, 'tie_break_en' => 6, 'duracion_estimada' => 75, 'es_predeterminado' => true],
            ['nombre' => 'Un set a 6 con tie-break', 'sets' => 1, 'juegos_por_set' => 6, 'tie_break_en' => 6, 'duracion_estimada' => 40],
            ['nombre' => 'Un set a 9', 'sets' => 1, 'juegos_por_set' => 9, 'tie_break_en' => 8, 'duracion_estimada' => 60],
            ['nombre' => 'Al mejor de 3 con super tie-break', 'sets' => 3, 'juegos_por_set' => 6, 'tie_break_en' => 6, 'super_tie_break_final' => true, 'duracion_estimada' => 60],
        ];
    }
}
