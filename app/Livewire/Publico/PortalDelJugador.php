<?php

namespace App\Livewire\Publico;

use App\Actions\Competicion\RegistrarResultado;
use App\Actions\Competicion\ValidarResultado;
use App\Actions\Jugadores\AnonimizarJugador;
use App\Actions\Jugadores\AnotarNoDisponibilidad;
use App\Actions\Jugadores\DarseDeBaja;
use App\Competicion\Errores\ErrorDeCompeticion;
use App\Competicion\Errores\ResultadoInvalido;
use App\Enums\EstadoInscripcion;
use App\Enums\EstadoPartido;
use App\Enums\EstadoTorneo;
use App\Enums\ResultadosPorJugadores;
use App\Filament\Competicion\Marcador;
use App\Livewire\Concerns\FormularioDeResultado;
use App\Models\Disponibilidad;
use App\Models\SuscripcionPush;
use App\Models\Inscripcion;
use App\Models\Jugador;
use App\Models\Partido;
use App\Models\Torneo;
use App\Publico\DatosDelTorneo;
use App\Publico\NombrePublico;
use App\Push\Push;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * El portal del jugador: sus inscripciones (con la baja mientras la
 * inscripción está abierta), sus partidos con hora y pista, registrar o
 * validar resultados si el torneo lo permite, cuándo no puede jugar, sus
 * datos y el borrado de su cuenta.
 */
#[Layout('components.layouts.publico')]
class PortalDelJugador extends Component
{
    use FormularioDeResultado;

    public string $nombre = '';

    public string $apellidos = '';

    public string $telefono = '';

    public string $alias = '';

    /** @var array<int, array{dia: string, desde: string, hasta: string}> torneo => intervalo nuevo */
    public array $noDisponible = [];

    public string $confirmarBorrado = '';

    public bool $avisosCorreo = true;

    public bool $avisosPush = true;

    public ?string $aviso = null;

    public function mount(): void
    {
        $yo = $this->yo();
        $this->nombre = $yo->nombre;
        $this->apellidos = $yo->apellidos;
        $this->telefono = (string) $yo->getAttribute('telefono');
        $this->alias = (string) $yo->alias;
        $this->avisosCorreo = (bool) ($yo->avisos['correo'] ?? true);
        $this->avisosPush = (bool) ($yo->avisos['push'] ?? true);
    }

    public function guardarAvisos(): void
    {
        $this->yo()->update(['avisos' => ['correo' => $this->avisosCorreo, 'push' => $this->avisosPush]]);
        $this->aviso = 'Avisos guardados.';
    }

    /** @param  array<string, mixed>  $suscripcion */
    public function guardarPush(array $suscripcion, ?string $dispositivo = null): void
    {
        try {
            app(Push::class)->guardar($suscripcion, $this->yo(), $dispositivo);
        } catch (InvalidArgumentException) {
            $this->addError('portal', 'No se han podido activar las notificaciones en este dispositivo.');
        }
    }

    public function quitarPush(string $endpoint): void
    {
        SuscripcionPush::query()->where('endpoint_hash', hash('sha256', $endpoint))->where('jugador_id', $this->yo()->id)->delete();
    }

    public function darmeDeBaja(int $inscripcion, DarseDeBaja $baja): void
    {
        $encontrada = Inscripcion::query()->find($inscripcion);
        abort_if($encontrada === null, 404);

        $this->accion(fn () => $baja->handle($this->yo(), $encontrada), 'Te has dado de baja.');
    }

    public function guardarResultado(RegistrarResultado $registrar): void
    {
        $partido = $this->misPartidos()->whereKey((int) $this->editando)->first();
        abort_if($partido === null, 404);

        try {
            $guardado = $registrar->handle($partido, $this->resultadoDelFormulario(), $this->yo());
        } catch (ResultadoInvalido $e) {
            $this->addError('resultado', implode(' ', $e->errores));

            return;
        } catch (ErrorDeCompeticion $e) {
            $this->addError('resultado', $e->getMessage());

            return;
        }

        $this->editando = null;
        $this->aviso = $guardado->estado === EstadoPartido::PendienteValidacion
            ? 'Resultado anotado. Falta que lo valide la pareja rival.'
            : 'Resultado anotado.';
    }

    public function validar(int $partido, ValidarResultado $validar): void
    {
        $encontrado = $this->misPartidos()->whereKey($partido)->first();
        abort_if($encontrado === null, 404);

        $this->accion(fn () => $validar->handle($encontrado, $this->yo()), 'Resultado validado.');
    }

    public function anotarNoDisponible(int $torneo, AnotarNoDisponibilidad $anotar): void
    {
        $datos = $this->noDisponible[$torneo] ?? [];
        $this->accion(fn () => $anotar->handle($this->yo(), Torneo::query()->findOrFail($torneo), (string) ($datos['dia'] ?? ''), substr((string) ($datos['desde'] ?? ''), 0, 5), substr((string) ($datos['hasta'] ?? ''), 0, 5)), 'Anotado: ese rato no te pondrán partidos.');
        unset($this->noDisponible[$torneo]);
    }

    public function quitarNoDisponible(int $id): void
    {
        $disponibilidad = Disponibilidad::query()->where('jugador_id', $this->yo()->id)->findOrFail($id);
        $this->accion(function () use ($disponibilidad): void {
            AnotarNoDisponibilidad::comprobar($this->yo(), $disponibilidad->torneo);
            $disponibilidad->delete();
        }, 'Quitado.');
    }

    public function guardarDatos(): void
    {
        $datos = $this->validate([
            'nombre' => ['required', 'string', 'max:80'],
            'apellidos' => ['required', 'string', 'max:120'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'alias' => ['nullable', 'string', 'max:60'],
        ]);

        $this->yo()->update(array_map(fn (mixed $v): ?string => is_string($v) && trim($v) !== '' ? trim($v) : null, $datos));
        $this->aviso = 'Datos guardados.';
    }

    public function borrarMisDatos(AnonimizarJugador $anonimizar): void
    {
        if (mb_strtoupper(trim($this->confirmarBorrado)) !== 'BORRAR') {
            $this->addError('confirmarBorrado', 'Escribe BORRAR para confirmar.');

            return;
        }

        $anonimizar->handle($this->yo());
        Auth::guard('jugador')->logout();
        session()->invalidate();
        session()->regenerateToken();

        $this->redirect('/');
    }

    public function render(): View
    {
        $yo = $this->yo();

        $inscripciones = Inscripcion::query()
            ->whereHas('participante.participanteJugadores', fn (Builder $q) => $q->where('jugador_id', $yo->id))
            ->with(['categoria.torneo', 'participante.participanteJugadores.jugador'])
            ->orderByDesc('id')->get();

        $partidos = $this->misPartidos()
            ->with(['fase.categoria.torneo', 'local.jugadores', 'visitante.jugadores', 'ganador', 'sets', 'franja', 'pista'])
            ->get()
            ->sortBy(fn (Partido $p): string => $p->franja !== null ? $p->franja->inicio->format('Y-m-d H:i') : '9999')
            ->values();

        $torneosAbiertosAlHorario = $inscripciones
            ->filter(fn (Inscripcion $i): bool => $i->estado !== EstadoInscripcion::Baja && $i->categoria->torneo->horarios_publicados_en === null && $i->categoria->torneo->estado !== EstadoTorneo::Finalizado)
            ->map(fn (Inscripcion $i): Torneo => $i->categoria->torneo)
            ->unique('id')->values();

        return view('livewire.publico.portal-del-jugador', [
            'yo' => $yo,
            'inscripciones' => $inscripciones,
            'partidos' => $partidos->map(fn (Partido $p): array => $this->filaDePartido($p, $yo))->all(),
            'torneosConHuecos' => $torneosAbiertosAlHorario,
            'noDisponibilidades' => Disponibilidad::query()->where('jugador_id', $yo->id)->orderBy('desde')->get()->groupBy('torneo_id'),
            'historial' => $yo->notifications()->latest()->limit(15)->get(),
        ]);
    }

    /**
     * Lo que el portal enseña de un partido y lo que el jugador puede hacer.
     *
     * @return array{id: int, torneo: string, categoria: string, texto: string, local: string, visitante: string, resultado: string, hora: string|null, estado: EstadoPartido, puedeAnotar: bool, puedeValidar: bool}
     */
    private function filaDePartido(Partido $p, Jugador $yo): array
    {
        $torneo = $p->fase->categoria->torneo;
        $datos = (new DatosDelTorneo($torneo))->partido($p);
        $local = NombrePublico::participante($p->local, $torneo) ?? Marcador::lado($p, true);
        $visitante = NombrePublico::participante($p->visitante, $torneo) ?? Marcador::lado($p, false);

        $registrador = $p->registrado_por_jugador_id;
        $miLadoEsLocal = $p->local !== null && in_array($yo->id, $p->local->idsDeJugadores(), true);
        $ladoDelRegistradorEsLocal = $registrador !== null && $p->local !== null && in_array($registrador, $p->local->idsDeJugadores(), true);

        return [
            'id' => $p->id,
            'torneo' => $torneo->nombre,
            'categoria' => $p->fase->categoria->nombre,
            'texto' => $datos['partido'],
            'local' => $local,
            'visitante' => $visitante,
            'resultado' => $datos['resultado'] !== '' ? $datos['resultado'] : ($p->estado === EstadoPartido::PendienteValidacion ? Marcador::resultado($p).' (sin validar)' : ''),
            'hora' => $datos['hora'],
            'estado' => $p->estado,
            'puedeAnotar' => $torneo->resultados_por_jugadores !== ResultadosPorJugadores::Desactivado
                && in_array($p->estado, [EstadoPartido::Pendiente, EstadoPartido::Programado, EstadoPartido::EnJuego], true)
                && $p->local_id !== null && $p->visitante_id !== null,
            'puedeValidar' => $p->estado === EstadoPartido::PendienteValidacion && $registrador !== null && $miLadoEsLocal !== $ladoDelRegistradorEsLocal,
        ];
    }

    /** @return Builder<Partido> */
    private function misPartidos(): Builder
    {
        $id = $this->yo()->id;

        return Partido::query()
            ->whereHas('fase.categoria.torneo', fn (Builder $q) => $q->whereNotIn('estado', [EstadoTorneo::Borrador->value]))
            ->where(fn (Builder $q) => $q
                ->whereHas('local.participanteJugadores', fn (Builder $l) => $l->where('jugador_id', $id))
                ->orWhereHas('visitante.participanteJugadores', fn (Builder $v) => $v->where('jugador_id', $id)));
    }

    private function accion(callable $hacer, string $hecho): void
    {
        try {
            $hacer();
        } catch (ErrorDeCompeticion $e) {
            $this->aviso = null;
            $this->addError('portal', $e->getMessage());

            return;
        }

        $this->resetErrorBag();
        $this->aviso = $hecho;
    }

    private function yo(): Jugador
    {
        $yo = Auth::guard('jugador')->user();
        abort_unless($yo instanceof Jugador, 403);

        return $yo;
    }
}
