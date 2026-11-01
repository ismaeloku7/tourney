<?php

namespace App\Exportaciones;

use App\Enums\TipoDeFase;
use App\Filament\Competicion\Marcador;
use App\Filament\MarcaDelOrganizador;
use App\Models\Categoria;
use App\Models\Fase;
use App\Models\Partido;
use Illuminate\Support\Str;

/**
 * El PDF de una categoría: por cada fase, los grupos con su clasificación
 * y sus partidos, o el cuadro ronda a ronda con los resultados.
 */
final class PdfDeCategoria
{
    public function generar(Categoria $categoria): string
    {
        $categoria->load(['torneo', 'fases.grupos.clasificaciones.participante', 'fases.grupos.partidos.local', 'fases.grupos.partidos.visitante', 'fases.grupos.partidos.sets']);

        $fases = $categoria->fases->map(fn (Fase $fase): array => [
            'fase' => $fase,
            'cuadro' => $fase->tipo->esDeCuadro() ? $this->rondas($fase) : [],
        ])->all();

        $html = view('exportaciones.categoria', [
            'categoria' => $categoria,
            'organizador' => MarcaDelOrganizador::nombre(),
            'fases' => $fases,
        ])->render();

        return Pdf::desdeHtml($html);
    }

    public function nombreDeArchivo(Categoria $categoria): string
    {
        return Str::slug($categoria->torneo->nombre.' '.$categoria->nombre).'.pdf';
    }

    /**
     * Las rondas del cuadro: nombre de la ronda y sus partidos ya escritos.
     *
     * @return list<array{nombre: string, partidos: list<array{local: string, visitante: string, resultado: string}>}>
     */
    private function rondas(Fase $fase): array
    {
        $partidos = $fase->partidos()->with(['local', 'visitante', 'ganador', 'sets'])->orderBy('ronda')->orderBy('posicion')->get();
        $rondas = (int) $partidos->where('es_tercer_puesto', false)->max('ronda');
        $salida = [];

        foreach ($partidos->where('es_tercer_puesto', false)->groupBy('ronda') as $ronda => $delaRonda) {
            $salida[] = [
                'nombre' => Marcador::ronda((int) $ronda, $rondas),
                'partidos' => $delaRonda->map(fn (Partido $p): array => $this->linea($p))->values()->all(),
            ];
        }

        $tercero = $partidos->firstWhere('es_tercer_puesto', true);
        if ($tercero !== null) {
            $salida[] = ['nombre' => 'Tercer puesto', 'partidos' => [$this->linea($tercero)]];
        }

        return $salida;
    }

    /** @return array{local: string, visitante: string, resultado: string} */
    private function linea(Partido $partido): array
    {
        return [
            'local' => Marcador::lado($partido, true),
            'visitante' => Marcador::lado($partido, false),
            'resultado' => $partido->cerrado() ? Marcador::resultado($partido) : '',
        ];
    }

    public static function tipoDeFase(Fase $fase): string
    {
        return $fase->tipo === TipoDeFase::Liga ? 'Liga' : $fase->tipo->etiqueta();
    }
}
