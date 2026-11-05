<?php

namespace App\Jugadores;

use App\Actions\Competicion\AnotarMarcador;
use App\Enums\EstadoPartido;
use App\Enums\EstadoTorneo;
use App\Enums\ResultadosPorJugadores;
use App\Filament\Competicion\Marcador;
use App\Models\Jugador;
use App\Models\Partido;
use App\Publico\DatosDelTorneo;
use App\Publico\NombrePublico;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Los partidos de un jugador tal como los enseña su portal: la consulta
 * (solo torneos publicados y fases visibles en la web), el orden (los que
 * tienen hora primero, por hora; después los demás) y lo que el jugador
 * puede hacer con cada uno.
 */
final class PartidosDelJugador
{
    /** Lo que ocupa la pista antes de este partido: «Ahora juegan X – Y (va 6-3 4-2)». */
    private function delante(Partido $p): ?string
    {
        if ($p->pista_id === null || $p->orden_en_pista === null) {
            return null;
        }

        $anterior = Partido::query()->where('pista_id', $p->pista_id)->where('orden_en_pista', '<', $p->orden_en_pista)
            ->whereIn('estado', [EstadoPartido::EnJuego->value, EstadoPartido::Pendiente->value, EstadoPartido::Programado->value])
            ->whereNotNull('franja_id')
            ->orderByDesc('orden_en_pista')->with(['local', 'visitante'])->first();

        if ($anterior === null) {
            return 'La pista está libre: os toca.';
        }

        $texto = ($anterior->estado === EstadoPartido::EnJuego ? 'Ahora juegan ' : 'Antes juegan ').Marcador::lado($anterior, true).' – '.Marcador::lado($anterior, false);
        $enVivo = AnotarMarcador::texto($anterior);

        return $texto.($enVivo !== '' ? " (va {$enVivo})" : '').'.';
    }

    /** @return Builder<Partido> */
    public function consulta(Jugador $yo): Builder
    {
        return Partido::query()
            ->whereHas('fase.categoria.torneo', fn (Builder $q) => $q->whereNotIn('estado', [EstadoTorneo::Borrador->value]))
            // Lo que la organización aún no enseña en la web tampoco sale en el portal.
            ->whereHas('fase', fn (Builder $q) => $q->where('visible_en_web', true))
            ->where(fn (Builder $q) => $q
                ->whereHas('local.participanteJugadores', fn (Builder $l) => $l->where('jugador_id', $yo->id))
                ->orWhereHas('visitante.participanteJugadores', fn (Builder $v) => $v->where('jugador_id', $yo->id)));
    }

    /** @return Collection<int, Partido> */
    public function todos(Jugador $yo): Collection
    {
        return $this->consulta($yo)
            ->with(['fase.categoria.torneo', 'local.jugadores', 'visitante.jugadores', 'ganador', 'sets', 'franja', 'pista'])
            ->get()
            ->sortBy(fn (Partido $p): string => ($p->franja !== null ? $p->franja->inicio->format('Y-m-d H:i') : '9999').'-'.str_pad((string) $p->id, 10, '0', STR_PAD_LEFT))
            ->values();
    }

    /** El próximo sin jugar: con hora, el más cercano; sin hora, el primero que queda. */
    public function proximo(Jugador $yo): ?Partido
    {
        return $this->todos($yo)->first(fn (Partido $p): bool => $this->porJugar($p));
    }

    public function porJugar(Partido $p): bool
    {
        return in_array($p->estado, [EstadoPartido::Pendiente, EstadoPartido::Programado, EstadoPartido::EnJuego], true)
            && $p->local_id !== null && $p->visitante_id !== null;
    }

    /**
     * Lo que el portal enseña de un partido y lo que el jugador puede hacer.
     *
     * @return array{id: int, torneo: string, categoria: string, texto: string, rival: string, local: string, visitante: string, resultado: string, hora: string|null, pista: string|null, estado: EstadoPartido, porJugar: bool, gane: bool|null, puedeAnotar: bool, puedeValidar: bool, encadenado: bool, enVivo: string, marcador: array{sets: list<array{0: int, 1: int}>, juegos: array{0: int, 1: int}}, puedeMarcar: bool, delante: string|null}
     */
    public function fila(Partido $p, Jugador $yo): array
    {
        $torneo = $p->fase->categoria->torneo;
        $datos = (new DatosDelTorneo($torneo))->partido($p);
        $local = NombrePublico::participante($p->local, $torneo) ?? Marcador::lado($p, true);
        $visitante = NombrePublico::participante($p->visitante, $torneo) ?? Marcador::lado($p, false);

        $registrador = $p->registrado_por_jugador_id;
        $miLadoEsLocal = $p->local !== null && in_array($yo->id, $p->local->idsDeJugadores(), true);
        $ladoDelRegistradorEsLocal = $registrador !== null && $p->local !== null && in_array($registrador, $p->local->idsDeJugadores(), true);
        $miParticipante = $miLadoEsLocal ? $p->local_id : $p->visitante_id;

        return [
            'id' => $p->id,
            'torneo' => $torneo->nombre,
            'categoria' => $p->fase->categoria->nombre,
            'texto' => $datos['partido'],
            'rival' => $miLadoEsLocal ? $visitante : $local,
            'local' => $local,
            'visitante' => $visitante,
            'resultado' => $datos['resultado'] !== '' ? $datos['resultado'] : ($p->estado === EstadoPartido::PendienteValidacion ? Marcador::resultado($p).' (sin validar)' : ''),
            'hora' => $datos['hora'],
            'pista' => $p->pista?->nombre,
            'estado' => $p->estado,
            'porJugar' => $this->porJugar($p),
            'gane' => $p->ganador_id === null ? null : $p->ganador_id === $miParticipante,
            'puedeAnotar' => $torneo->resultados_por_jugadores !== ResultadosPorJugadores::Desactivado && $this->porJugar($p),
            'puedeValidar' => $p->estado === EstadoPartido::PendienteValidacion && $registrador !== null && $miLadoEsLocal !== $ladoDelRegistradorEsLocal,
            'encadenado' => $torneo->encadenado(),
            'enVivo' => AnotarMarcador::texto($p),
            'marcador' => $p->marcador_en_vivo ?? ['sets' => [], 'juegos' => [0, 0]],
            'puedeMarcar' => $torneo->encadenado() && $torneo->resultados_por_jugadores !== ResultadosPorJugadores::Desactivado && $this->porJugar($p),
            'delante' => $torneo->encadenado() && $this->porJugar($p) && $p->estado !== EstadoPartido::EnJuego ? $this->delante($p) : null,
        ];
    }
}
