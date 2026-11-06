<?php

namespace App\Livewire\Jugador;

use App\Actions\Jugadores\AnotarNoDisponibilidad;
use App\Actions\Jugadores\CambiarNivel;
use App\Actions\Jugadores\DarseDeBaja;
use App\Competicion\Errores\ErrorDeCompeticion;
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

/**
 * Un torneo del jugador: su pareja (con los niveles, que cualquiera de
 * los dos pone), sus partidos, su grupo y los demás grupos de su
 * categoría, el cuadro, cuándo no puede jugar y la baja mientras la
 * inscripción siga abierta.
 */
#[Layout('components.layouts.jugador')]
class MiTorneo extends PaginaDelJugador
{
    use ConResultados;

    public Inscripcion $inscripcion;

    /** @var array<int, string> jugador => nivel */
    public array $niveles = [];

    /** @var array{dia: string, desde: string, hasta: string}|array{} */
    public array $noDisponible = [];

    public function mount(Inscripcion $inscripcion): void
    {
        $inscripcion->load(['categoria.torneo.sede', 'participante.jugadores']);
        abort_unless($inscripcion->participante->jugadores->contains(fn (Jugador $j): bool => $j->is($this->yo())), 404);

        $this->inscripcion = $inscripcion;
        foreach ($inscripcion->participante->jugadores as $jugador) {
            $this->niveles[$jugador->id] = (string) $jugador->nivel_declarado;
        }
    }

    public function guardarNiveles(CambiarNivel $cambiar): void
    {
        abort_unless($this->editable(), 403);

        $this->aviso = null;
        $this->resetErrorBag();
        $hayErrores = false;

        foreach ($this->inscripcion->participante->jugadores()->get() as $jugador) {
            $nuevo = trim($this->niveles[$jugador->id] ?? '');
            if ($nuevo === (string) $jugador->nivel_declarado) {
                continue;
            }

            try {
                $cambiar->handle($this->yo(), $jugador, $nuevo);
            } catch (ErrorDeCompeticion $e) {
                $this->addError('niveles.'.$jugador->id, $e->getMessage());
                $hayErrores = true;
            }
        }

        if (! $hayErrores) {
            $this->aviso = 'Niveles guardados.';
        }
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

    public function editable(): bool
    {
        return $this->inscripcion->estado !== EstadoInscripcion::Baja
            && ! in_array($this->torneo()->estado, [EstadoTorneo::Finalizado, EstadoTorneo::Archivado], true);
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

        $filas = $partidos->todos($yo)->filter(fn (Partido $p): bool => $p->local_id === $participante->id || $p->visitante_id === $participante->id)->map(fn (Partido $p): array => $partidos->fila($p, $yo))->values();
        $deMiCategoria = fn (array $bloque): bool => $bloque['categoria']->id === $categoriaId;

        return view('livewire.jugador.mi-torneo', [
            'yo' => $yo,
            'torneo' => $torneo,
            'jugadores' => $participante->jugadores,
            'media' => $participante->nivelMedioTexto(),
            'editable' => $this->editable(),
            'puedeDarseDeBaja' => $torneo->estado === EstadoTorneo::InscripcionAbierta && $this->inscripcion->estado !== EstadoInscripcion::Baja,
            'porJugar' => $filas->filter(fn (array $p): bool => $p['porJugar'])->values()->all(),
            'jugados' => $filas->reject(fn (array $p): bool => $p['porJugar'])->values()->all(),
            'miGrupo' => $miGrupo,
            'otrosGrupos' => $otrosGrupos,
            // Los cuadros ya hechos y, mientras se juegan los grupos, cómo quedaría el que va detrás.
            'cuadros' => $cuadros = [...array_values(array_filter($datos->cuadros(), $deMiCategoria)), ...array_values(array_filter($datos->cuadrosPrevistos(), $deMiCategoria))],
            'cuadrosQueFaltan' => $this->cuadrosQueFaltan($categoriaId, array_map(fn (array $bloque): int => $bloque['fase']->id, $cuadros)),
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
