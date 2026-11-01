<?php

namespace App\Filament\Pages;

use App\Actions\Competicion\ValidarResultado;
use App\Actions\Inscripciones\CambiarEstadoDeInscripcion;
use App\Competicion\Errores\ErrorDeCompeticion;
use App\Enums\EstadoInscripcion;
use App\Enums\EstadoPartido;
use App\Enums\EstadoTorneo;
use App\Filament\Avisos;
use App\Filament\Competicion\Marcador;
use App\Filament\Resources\Torneos\Pages\PartidosDelTorneo;
use App\Filament\Resources\Torneos\TorneoResource;
use App\Models\Inscripcion;
use App\Models\Jugador;
use App\Models\Partido;
use App\Models\RegistroDeCambio;
use App\Models\Torneo;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * El inicio del panel: arriba, la organización de un vistazo (torneos, el
 * próximo torneo y cómo van los partidos); debajo, la competición del día,
 * lo que espera una decisión, la actividad reciente, el mes y las plazas.
 * Un administrador de torneo solo ve los suyos.
 */
class Panel extends Dashboard
{
    protected static ?string $title = 'Panel';

    protected static ?string $navigationLabel = 'Panel';

    protected string $view = 'filament.pages.escritorio';

    /** Días desde hoy del día que se enseña en «Partidos del día». */
    public int $diasDesdeHoy = 0;

    /** Página de «Por revisar». */
    public int $paginaDeRevision = 0;

    private const POR_PAGINA = 3;

    public function getHeading(): string|Htmlable
    {
        return '';
    }

    /** @return array<class-string> */
    public function getWidgets(): array
    {
        return [];
    }

    public function diaAnterior(): void
    {
        $this->diasDesdeHoy--;
    }

    public function diaSiguiente(): void
    {
        $this->diasDesdeHoy++;
    }

    public function revisionAnterior(): void
    {
        $this->paginaDeRevision = max(0, $this->paginaDeRevision - 1);
    }

    public function revisionSiguiente(): void
    {
        $this->paginaDeRevision++;
    }

    public function confirmarInscripcion(int $id): void
    {
        $this->cambiarInscripcion($id, EstadoInscripcion::Confirmada, 'Pareja confirmada');
    }

    public function darDeBaja(int $id): void
    {
        $this->cambiarInscripcion($id, EstadoInscripcion::Baja, 'Pareja dada de baja');
    }

    public function validarResultado(int $id, ValidarResultado $validar): void
    {
        $partido = $this->partidos()->whereKey($id)->first();
        abort_if($partido === null, 404);

        try {
            $validar->handle($partido, $this->usuario());
        } catch (ErrorDeCompeticion $e) {
            Avisos::error('No se ha podido validar', [$e->getMessage()]);

            return;
        }

        Avisos::hecho('Resultado validado');
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $torneos = $this->torneos()->get();
        $activos = $torneos->filter(fn (Torneo $t): bool => in_array($t->estado, [EstadoTorneo::InscripcionAbierta, EstadoTorneo::InscripcionCerrada, EstadoTorneo::Sorteo, EstadoTorneo::EnJuego], true));
        $proximo = $torneos
            ->filter(fn (Torneo $t): bool => ! in_array($t->estado, [EstadoTorneo::Finalizado, EstadoTorneo::Archivado], true) && $t->fecha_fin->greaterThanOrEqualTo(today()))
            ->sortBy(fn (Torneo $t): string => $t->fecha_inicio->toDateString())
            ->first();

        return [
            'torneos' => [
                'activos' => $activos->count(),
                'enJuego' => $torneos->where('estado', EstadoTorneo::EnJuego)->count(),
                'abiertos' => $torneos->where('estado', EstadoTorneo::InscripcionAbierta)->count(),
                'borradores' => $torneos->where('estado', EstadoTorneo::Borrador)->count(),
                'crear' => TorneoResource::canCreate() ? TorneoResource::getUrl('create') : null,
                'lista' => TorneoResource::getUrl('index'),
            ],
            'proximo' => $proximo !== null ? $this->datosDelProximo($proximo) : null,
            'partidos' => $this->cuentaDePartidos($activos),
            'dia' => $this->partidosDelDia(),
            'revision' => $this->porRevisar(),
            'actividad' => $this->actividad(),
            'mes' => $this->elMes(),
            'plazas' => $this->plazas($activos),
        ];
    }

    /** @return array<string, mixed> */
    private function datosDelProximo(Torneo $torneo): array
    {
        $dias = (int) today()->diffInDays($torneo->fecha_inicio, false);
        $pendientes = Inscripcion::query()->whereIn('categoria_id', $torneo->categorias()->pluck('id')->all())->where('estado', EstadoInscripcion::Pendiente->value)->count();

        return [
            'nombre' => $torneo->nombre,
            'cuando' => match (true) {
                $dias > 1 => ['En', $dias, 'días'],
                $dias === 1 => ['', 'Mañana', ''],
                $dias === 0 => ['', 'Hoy', ''],
                default => ['', 'En juego', ''],
            },
            'fecha' => $torneo->fecha_inicio->isSameDay($torneo->fecha_fin)
                ? $torneo->fecha_inicio->translatedFormat('j \d\e F, l')
                : $torneo->fecha_inicio->translatedFormat('j').'–'.$torneo->fecha_fin->translatedFormat('j \d\e F'),
            'estado' => $torneo->estado->etiqueta(),
            'pendientes' => $pendientes,
            'parejas' => TorneoResource::getUrl('edit', ['record' => $torneo, 'relation' => '1']),
            'ficha' => TorneoResource::getUrl('edit', ['record' => $torneo]),
        ];
    }

    /**
     * @param  Collection<int, Torneo>  $activos
     * @return array{jugados: int, total: int, porValidar: int, mesa: string|null}
     */
    private function cuentaDePartidos(Collection $activos): array
    {
        $ids = $activos->pluck('id')->all();
        $enJuego = $activos->first(fn (Torneo $t): bool => $t->estado === EstadoTorneo::EnJuego) ?? $activos->first();

        return [
            'jugados' => self::partidosDe($ids)->whereIn('estado', [EstadoPartido::Jugado->value, EstadoPartido::Walkover->value])->count(),
            'total' => self::partidosDe($ids)->count(),
            'porValidar' => self::partidosDe($ids)->where('estado', EstadoPartido::PendienteValidacion->value)->count(),
            'mesa' => $enJuego !== null ? PartidosDelTorneo::getUrl(['record' => $enJuego]) : null,
        ];
    }

    /** @return array<string, mixed> */
    private function partidosDelDia(): array
    {
        $dia = CarbonImmutable::today()->addDays($this->diasDesdeHoy);

        $partidos = $this->partidos()
            ->whereHas('franja', fn (Builder $q) => $q->whereDate('inicio', $dia->toDateString()))
            ->with(['franja', 'pista', 'local', 'visitante', 'sets', 'fase.categoria'])
            ->get()
            ->sortBy(fn (Partido $p): string => $p->franja?->inicio->format('H:i') ?? '99')
            ->values();

        $jugados = $partidos->filter(fn (Partido $p): bool => in_array($p->estado, [EstadoPartido::Jugado, EstadoPartido::Walkover], true))->count();
        $siguiente = $partidos->first(fn (Partido $p): bool => in_array($p->estado, [EstadoPartido::Pendiente, EstadoPartido::Programado], true) && $p->franja !== null && $p->franja->inicio->isFuture());

        return [
            'titulo' => ucfirst($dia->translatedFormat('D j M')).match ($this->diasDesdeHoy) {
                0 => ' (hoy)',
                1 => ' (mañana)',
                -1 => ' (ayer)',
                default => '',
            },
            'total' => $partidos->count(),
            'jugados' => $jugados,
            'siguiente' => $siguiente?->franja?->inicio->format('H:i'),
            'lista' => $partidos->take(8)->map(fn (Partido $p): array => [
                'hora' => $p->franja?->inicio->format('H:i') ?? '—',
                'pista' => $p->pista->nombre ?? '',
                'categoria' => $p->fase->categoria->nombre,
                'local' => Marcador::lado($p, true),
                'visitante' => Marcador::lado($p, false),
                'resultado' => in_array($p->estado, [EstadoPartido::Jugado, EstadoPartido::Walkover, EstadoPartido::PendienteValidacion], true) ? Marcador::resultado($p) : null,
                'estado' => $p->estado->etiqueta(),
                'color' => $p->estado->color(),
            ])->all(),
            'mas' => max(0, $partidos->count() - 8),
        ];
    }

    /** @return array{total: int, pagina: int, paginas: int, items: list<array<string, mixed>>} */
    private function porRevisar(): array
    {
        $torneos = $this->torneos()->pluck('id')->all();

        $inscripciones = Inscripcion::query()
            ->where('estado', EstadoInscripcion::Pendiente->value)
            ->whereHas('categoria', fn (Builder $q) => $q->whereIn('torneo_id', $torneos))
            ->whereHas('participante', fn (Builder $q) => $q->has('participanteJugadores', '>=', 2))
            ->with(['categoria.torneo', 'participante'])
            ->orderBy('id')
            ->get()
            ->map(fn (Inscripcion $i): array => [
                'tipo' => 'inscripcion',
                'id' => $i->id,
                'quien' => $i->participante->nombre,
                'texto' => 'pide plaza en',
                'donde' => $i->categoria->nombre.' · '.$i->categoria->torneo->nombre,
                'cuando' => $i->created_at?->diffForHumans(),
            ]);

        $resultados = $this->partidos()
            ->where('estado', EstadoPartido::PendienteValidacion->value)
            ->with(['local', 'visitante', 'sets', 'fase.categoria'])
            ->orderBy('id')
            ->get()
            ->map(fn (Partido $p): array => [
                'tipo' => 'resultado',
                'id' => $p->id,
                'quien' => Marcador::lado($p, true).' – '.Marcador::lado($p, false),
                'texto' => 'tienen un resultado sin validar:',
                'donde' => Marcador::resultado($p).' · '.$p->fase->categoria->nombre,
                'cuando' => $p->updated_at?->diffForHumans(),
            ]);

        $todo = $resultados->concat($inscripciones)->values();
        $paginas = max(1, (int) ceil($todo->count() / self::POR_PAGINA));
        $this->paginaDeRevision = min($this->paginaDeRevision, $paginas - 1);

        return [
            'total' => $todo->count(),
            'pagina' => $this->paginaDeRevision,
            'paginas' => $paginas,
            'items' => array_values($todo->slice($this->paginaDeRevision * self::POR_PAGINA, self::POR_PAGINA)->all()),
        ];
    }

    /** @return list<array{texto: string, cuando: string}> */
    private function actividad(): array
    {
        return array_values(RegistroDeCambio::query()
            ->with(['usuario', 'jugador'])
            ->latest('id')
            ->limit(6)
            ->get()
            ->map(fn (RegistroDeCambio $r): array => [
                'texto' => self::frase($r),
                'cuando' => $r->created_at->diffForHumans(),
            ])
            ->all());
    }

    private static function frase(RegistroDeCambio $registro): string
    {
        $quien = $registro->usuario !== null ? $registro->usuario->nombre : ($registro->jugador !== null ? $registro->jugador->nombre : 'Alguien');
        $despues = $registro->despues ?? [];

        $que = match ($registro->accion) {
            'importar_parejas' => 'ha importado '.($despues['parejas'] ?? 0).' parejas',
            'inscribir' => 'ha inscrito a '.($despues['participante'] ?? 'una pareja'),
            'cambiar_inscripcion' => 'ha pasado una inscripción a «'.(EstadoInscripcion::tryFrom((string) ($despues['estado'] ?? ''))?->etiqueta() ?? 'otro estado').'»',
            'cambiar_nivel' => 'ha cambiado un nivel a '.($despues['nivel'] ?? '—'),
            'registrar_resultado' => 'ha anotado un resultado',
            'validar_resultado' => 'ha validado un resultado',
            'anular_resultado' => 'ha anulado un resultado',
            'sortear_grupos' => 'ha sorteado los grupos',
            'generar_partidos' => 'ha generado los partidos',
            'planificar_horarios' => 'ha planificado los horarios',
            'cambiar_estado' => 'ha cambiado el estado de un torneo',
            'baja_del_jugador' => 'se ha dado de baja',
            'confirmar_invitacion' => 'ha confirmado su pareja',
            'marcar_pago' => 'ha anotado un pago',
            'duplicar_categoria' => 'ha duplicado una categoría',
            'completar_pareja' => 'ha completado una pareja',
            'ajustes' => 'ha cambiado los ajustes',
            default => 'ha hecho un cambio ('.str_replace('_', ' ', $registro->accion).')',
        };

        return "{$quien} {$que}.";
    }

    /** @return array<string, mixed> */
    private function elMes(): array
    {
        $desde = today()->startOfMonth();
        $torneos = $this->torneos()->pluck('id')->all();

        return [
            'titulo' => ucfirst($desde->translatedFormat('F Y')),
            'inscripciones' => Inscripcion::query()->whereHas('categoria', fn (Builder $q) => $q->whereIn('torneo_id', $torneos))->where('created_at', '>=', $desde)->count(),
            'confirmadas' => Inscripcion::query()->whereHas('categoria', fn (Builder $q) => $q->whereIn('torneo_id', $torneos))->where('confirmada_en', '>=', $desde)->count(),
            'partidos' => $this->partidos()->whereIn('estado', [EstadoPartido::Jugado->value, EstadoPartido::Walkover->value])->where('updated_at', '>=', $desde)->count(),
            'jugadores' => Jugador::query()->where('created_at', '>=', $desde)->count(),
            'torneos' => TorneoResource::getUrl('index'),
        ];
    }

    /**
     * @param  Collection<int, Torneo>  $activos
     * @return array{ocupadas: int, cupo: int, espera: int, sinCupo: int}
     */
    private function plazas(Collection $activos): array
    {
        $ocupadas = 0;
        $cupo = 0;
        $espera = 0;
        $sinCupo = 0;

        foreach ($activos as $torneo) {
            foreach ($torneo->categorias()->withCount([
                'inscripciones as confirmadas_count' => fn (Builder $q) => $q->where('estado', EstadoInscripcion::Confirmada->value),
                'inscripciones as espera_count' => fn (Builder $q) => $q->where('estado', EstadoInscripcion::ListaDeEspera->value),
            ])->get() as $categoria) {
                $confirmadas = (int) $categoria->getAttribute('confirmadas_count');
                $espera += (int) $categoria->getAttribute('espera_count');

                if ($categoria->cupo === null) {
                    $sinCupo += $confirmadas;

                    continue;
                }

                $ocupadas += $confirmadas;
                $cupo += $categoria->cupo;
            }
        }

        return ['ocupadas' => $ocupadas, 'cupo' => $cupo, 'espera' => $espera, 'sinCupo' => $sinCupo];
    }

    /** @return Builder<Torneo> */
    private function torneos(): Builder
    {
        /** @var Builder<Torneo> $consulta */
        $consulta = TorneoResource::getEloquentQuery();

        return $consulta;
    }

    /**
     * Los partidos no anulados de esos torneos.
     *
     * @param  array<int, mixed>  $torneos
     * @return Builder<Partido>
     */
    private static function partidosDe(array $torneos): Builder
    {
        return Partido::query()->whereHas('fase.categoria', fn (Builder $q) => $q->whereIn('torneo_id', $torneos))->where('estado', '!=', EstadoPartido::Anulado->value);
    }

    /** @return Builder<Partido> */
    private function partidos(): Builder
    {
        $torneos = $this->torneos()->pluck('id')->all();

        return Partido::query()->whereHas('fase.categoria', fn (Builder $q) => $q->whereIn('torneo_id', $torneos));
    }

    private function cambiarInscripcion(int $id, EstadoInscripcion $estado, string $hecho): void
    {
        $torneos = $this->torneos()->pluck('id')->all();
        $inscripcion = Inscripcion::query()->whereHas('categoria', fn (Builder $q) => $q->whereIn('torneo_id', $torneos))->find($id);
        abort_if($inscripcion === null, 404);

        try {
            app(CambiarEstadoDeInscripcion::class)->handle($inscripcion, $estado, $this->usuario(), avisar: false);
        } catch (ErrorDeCompeticion $e) {
            Avisos::error('No se ha podido cambiar', [$e->getMessage()]);

            return;
        }

        Avisos::hecho($hecho, $inscripcion->participante->nombre);
    }

    private function usuario(): User
    {
        $usuario = Filament::auth()->user();
        assert($usuario instanceof User);

        return $usuario;
    }
}
