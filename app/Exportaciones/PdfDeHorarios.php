<?php

namespace App\Exportaciones;

use App\Filament\Competicion\Marcador;
use App\Filament\MarcaDelOrganizador;
use App\Filament\Resources\Torneos\Pages\PartidosDelTorneo;
use App\Models\Partido;
use App\Models\Torneo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Los horarios del torneo en PDF: un bloque por día con los partidos en
 * orden de hora (hora, pista, categoría, fase, partido). Los partidos sin
 * hora, al final.
 */
final class PdfDeHorarios
{
    public function generar(Torneo $torneo): string
    {
        $partidos = Partido::query()
            ->whereHas('fase.categoria', fn (Builder $q) => $q->where('torneo_id', $torneo->id))
            ->whereNotNull('local_id')->whereNotNull('visitante_id')
            ->with(['fase.categoria', 'grupo', 'local', 'visitante', 'ganador', 'pista', 'franja', 'sets'])
            ->get()
            ->sortBy(fn (Partido $p): string => ($p->franja !== null ? $p->franja->inicio->format('Y-m-d H:i') : '9999').'-'.str_pad((string) $p->id, 8, '0', STR_PAD_LEFT))
            ->values();

        $porDia = $partidos->groupBy(fn (Partido $p): string => $p->franja !== null ? $p->franja->inicio->translatedFormat('l j \d\e F') : 'Sin hora')
            ->map(fn ($delDia) => $delDia->map(fn (Partido $p): array => [
                'hora' => $p->franja !== null ? $p->franja->inicio->format('H:i') : '—',
                'pista' => $p->pista !== null ? $p->pista->nombre : '—',
                'categoria' => $p->fase->categoria->nombre,
                'fase' => $p->fase->nombre.' · '.PartidosDelTorneo::donde($p),
                'partido' => Marcador::lado($p, true).' – '.Marcador::lado($p, false),
                'resultado' => $p->cerrado() ? Marcador::resultado($p) : '',
            ])->all())
            ->all();

        $html = view('exportaciones.horarios', [
            'torneo' => $torneo,
            'organizador' => MarcaDelOrganizador::nombre(),
            'porDia' => $porDia,
        ])->render();

        return Pdf::desdeHtml($html, horizontal: true);
    }

    public function nombreDeArchivo(Torneo $torneo): string
    {
        return Str::slug($torneo->nombre.' horarios').'.pdf';
    }
}
