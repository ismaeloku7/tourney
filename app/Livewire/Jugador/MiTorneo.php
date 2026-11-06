<?php

namespace App\Livewire\Jugador;

use App\Actions\Jugadores\AnotarNoDisponibilidad;
use App\Actions\Jugadores\DarseDeBaja;
use App\Enums\EstadoInscripcion;
use App\Enums\EstadoTorneo;
use App\Enums\ReglaDeCruces;
use App\Enums\TipoDeFase;
use App\Jugadores\PartidosDelJugador;
use App\Models\Disponibilidad;
use App\Models\Fase;
use App\Models\Inscripcion;
use App\Models\Jugador;
use App\Models\Partido;
use App\Models\Torneo;
use App\Publico\DatosDelTorneo;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;

/**
 * Un torneo del jugador: su próximo partido, sus partidos, su grupo y los
 * demás grupos de su categoría (y de otras), el cuadro, cuándo no puede
 * jugar y la baja mientras la inscripción siga abierta. Los niveles se
 * cambian en «Mis datos».
 */
#[Layout('components.layouts.jugador')]
class MiTorneo extends PaginaDelJugador
{
    use ConResultados;

    public Inscripcion $inscripcion;

    /** La otra categoría del torneo que se está mirando (sus grupos y su cuadro). */
    #[Url(as: 'ver')]
    public ?int $verCategoria = null;

    /** @var array{dia: string, desde: string, hasta: string}|array{} */
    public array $noDisponible = [];

    public function mount(Inscripcion $inscripcion): void
    {
        $inscripcion->load(['categoria.torneo.sede', 'participante.jugadores']);
        abort_unless($inscripcion->participante->jugadores->contains(fn (Jugador $j): bool => $j->is($this->yo())), 404);

        $this->inscripcion = $inscripcion;
    }

    public function darmeDeBaja(DarseDeBaja $baja): void
    {
        $this->accion(fn () => $baja->handle($this->yo(), $this->inscripcion), 'Te has dado de baja.');
        $this->inscripcion->refresh();
    }

    public function anotarNoDisponible(AnotarNoDisponibilidad $anotar): void
    {
        $datos = $this->noDisponible;
        $this->accion(fn () => $anotar->handle($this->yo(), $this->torneo(), (string) ($datos['dia'] ?? ''), substr((string) ($datos['desde'] ?? ''), 0, 5), substr((string) ($datos['hasta'] ?? ''), 0, 5)), 'Anotado: ese rato no te pondrán partidos.');
        $this->noDisponible = [];
    }

    public function quitarNoDisponible(int $id): void
    {
        $disponibilidad = Disponibilidad::query()->where('jugador_id', $this->yo()->id)->where('torneo_id', $this->torneo()->id)->findOrFail($id);
        $this->accion(function () use ($disponibilidad): void {
            AnotarNoDisponibilidad::comprobar($this->yo(), $disponibilidad->torneo);
            $disponibilidad->delete();
        }, 'Quitado.');
    }

    public function render(PartidosDelJugador $partidos): View
    {
        $yo = $this->yo();
        $torneo = $this->torneo();
        $participante = $this->inscripcion->participante->load('jugadores');
        $datos = new DatosDelTorneo($torneo->load(['categorias', 'sede']));
        $categoriaId = $this->inscripcion->categoria_id;

        $grupos = array_values(array_filter($datos->grupos(), fn (array $bloque): bool => $bloque['categoria']->id === $categoriaId));
        $miGrupo = null;
        $otrosGrupos = [];
        foreach ($grupos as $bloque) {
            foreach ($bloque['grupos'] as $grupo) {
                $esMio = collect($grupo['lineas'])->contains('participante', $participante->id);
                if ($esMio && $miGrupo === null) {
                    $miGrupo = ['fase' => $bloque['fase'], 'cruces' => $bloque['cruces']] + $grupo;
                } else {
                    $otrosGrupos[] = ['fase' => $bloque['fase'], 'cruces' => $bloque['cruces']] + $grupo;
                }
            }
        }

        // Las demás categorías con algo que enseñar (grupos o cuadro), para mirarlas.
        $grupos = $datos->grupos();
        $todosLosCuadros = [...$datos->cuadros(), ...$datos->cuadrosPrevistos()];
        $otrasCategorias = [];
        foreach ([...$grupos, ...$todosLosCuadros] as $bloque) {
            if ($bloque['categoria']->id !== $categoriaId) {
                $otrasCategorias[$bloque['categoria']->id] = $bloque['categoria']->nombre;
            }
        }
        $explorar = null;
        if ($this->verCategoria !== null && isset($otrasCategorias[$this->verCategoria])) {
            $deEsa = fn (array $bloque): bool => $bloque['categoria']->id === $this->verCategoria;
            $explorar = ['grupos' => [], 'cuadros' => array_values(array_filter($todosLosCuadros, $deEsa))];
            foreach (array_filter($grupos, $deEsa) as $bloque) {
                foreach ($bloque['grupos'] as $grupo) {
                    $explorar['grupos'][] = ['fase' => $bloque['fase'], 'cruces' => $bloque['cruces']] + $grupo;
                }
            }
        }

        $filas = $partidos->todos($yo)->filter(fn (Partido $p): bool => $p->local_id === $participante->id || $p->visitante_id === $participante->id)->map(fn (Partido $p): array => $partidos->fila($p, $yo))->values();
        $deMiCategoria = fn (array $bloque): bool => $bloque['categoria']->id === $categoriaId;

        return view('livewire.jugador.mi-torneo', [
            'yo' => $yo,
            'torneo' => $torneo,
            'equipo' => $participante->nombre,
            'puedeDarseDeBaja' => $torneo->estado === EstadoTorneo::InscripcionAbierta && $this->inscripcion->estado !== EstadoInscripcion::Baja,
            'porJugar' => $porJugar = $filas->filter(fn (array $p): bool => $p['porJugar'])->values()->all(),
            'proximo' => $porJugar[0] ?? null,
            'jugados' => $filas->reject(fn (array $p): bool => $p['porJugar'])->values()->all(),
            'miGrupo' => $miGrupo,
            'otrosGrupos' => $otrosGrupos,
            // Los cuadros ya hechos y, mientras se juegan los grupos, cómo quedaría el que va detrás.
            'cuadros' => $cuadros = [...array_values(array_filter($datos->cuadros(), $deMiCategoria)), ...array_values(array_filter($datos->cuadrosPrevistos(), $deMiCategoria))],
            'cuadrosQueFaltan' => $this->cuadrosQueFaltan($categoriaId, array_map(fn (array $bloque): int => $bloque['fase']->id, $cuadros)),
            'otrasCategorias' => $otrasCategorias,
            'explorar' => $explorar,
            // Su categoría tiene fases, pero ninguna se enseña todavía.
            'faseOculta' => $this->inscripcion->categoria->fases()->exists() && $this->inscripcion->categoria->fases()->where('visible_en_web', true)->doesntExist(),
            'admiteDisponibilidad' => $torneo->horarios_publicados_en === null && ! in_array($torneo->estado, [EstadoTorneo::Finalizado, EstadoTorneo::Archivado], true) && $this->inscripcion->estado !== EstadoInscripcion::Baja,
            'noDisponibilidades' => Disponibilidad::query()->where('jugador_id', $yo->id)->where('torneo_id', $torneo->id)->orderBy('desde')->get(),
        ])->title($torneo->nombre);
    }

    /**
     * Los cuadros de la categoría que se enseñan en la web pero aún no
     * tienen nada que ver, cada uno con por qué: así el jugador sabe que
     * el cuadro existe y qué falta para que se llene.
     *
     * @param  list<int>  $yaEnsenados
     * @return list<array{fase: Fase, motivo: string}>
     */
    private function cuadrosQueFaltan(int $categoriaId, array $yaEnsenados): array
    {
        $fases = Fase::query()->where('categoria_id', $categoriaId)->where('visible_en_web', true)->orderBy('orden')->get();
        $faltan = [];

        foreach ($fases as $fase) {
            if (! $fase->tipo->esDeCuadro() || in_array($fase->id, $yaEnsenados, true) || $fase->partidos()->exists()) {
                continue;
            }

            $anterior = $fases->last(fn (Fase $f): bool => $f->orden < $fase->orden && $f->tipo->esDeGrupos());
            $motivo = match (true) {
                $anterior === null => 'Se llenará cuando la organización haga el sorteo.',
                $anterior->grupos()->doesntExist() => 'Se llenará cuando se sorteen los grupos y acabe la fase de grupos.',
                $fase->tipo === TipoDeFase::Eliminatoria && $fase->configuracionDeEliminatoria()->reglaDeCruces === ReglaDeCruces::Sorteo => 'Los cruces se sortean cuando acabe la fase de grupos: hasta entonces no se puede adelantar.',
                default => 'Se llenará cuando acabe la fase de grupos.',
            };

            $faltan[] = ['fase' => $fase, 'motivo' => $motivo];
        }

        return $faltan;
    }

    private function torneo(): Torneo
    {
        return $this->inscripcion->categoria->torneo;
    }
}
