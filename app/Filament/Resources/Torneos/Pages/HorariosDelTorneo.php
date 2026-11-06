<?php

namespace App\Filament\Resources\Torneos\Pages;

use App\Actions\Competicion\DuracionesAprendidas;
use App\Actions\Competicion\HorasEnDirecto;
use App\Actions\Competicion\PlanificarHorarios;
use App\Competicion\Datos\DisponibilidadDePista;
use App\Competicion\Datos\PropuestaDeHorarios;
use App\Competicion\Datos\SugerenciaDeRecolocacion;
use App\Competicion\Errores\ConfiguracionInvalida;
use App\Competicion\Errores\ErrorDeCompeticion;
use App\Competicion\Errores\OperacionNoPermitida;
use App\Competicion\MoverEnElHorario;
use App\Competicion\PlanificadorContinuo;
use App\Enums\EstadoPartido;
use App\Filament\Avisos;
use App\Filament\Competicion\Marcador;
use App\Filament\Resources\Torneos\TorneoResource;
use App\Models\Franja;
use App\Models\Partido;
use App\Models\Pista;
use App\Models\RegistroDeCambio;
use App\Models\Torneo;
use App\Models\TorneoPista;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Los horarios del torneo, pista a pista, como una línea de tiempo. Con
 * «Calcular» se ve el plan propuesto (cuándo acaba, cómo de ocupadas van
 * las pistas, lo que no cabe) sin guardar nada; solo «Aplicar» lo guarda.
 */
class HorariosDelTorneo extends Page
{
    use InteractsWithRecord;

    protected static string $resource = TorneoResource::class;

    protected static ?string $breadcrumb = 'Horarios';

    protected string $view = 'filament.panel.torneos.horarios';

    /**
     * El plan calculado y sin guardar (PropuestaDeHorarios::toArray()), o
     * null para ver los horarios guardados.
     *
     * @var array<string, mixed>|null
     */
    public ?array $propuesta = null;

    /**
     * Las sugerencias de recolocación que se han ignorado en esta visita.
     *
     * @var list<string>
     */
    public array $ignoradas = [];

    /** Colores de las categorías en la línea de tiempo, por su orden. */
    private const COLORES = ['#3a8ee0', '#f26b3a', '#2fb380', '#9b6bd6', '#f5b62f', '#e0547a', '#3bb3c4', '#6d7a84'];

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        abort_unless(Gate::allows('view', $this->torneo()), 403);
    }

    public function getTitle(): string|Htmlable
    {
        return 'Horarios · '.$this->torneo()->nombre;
    }

    public function getSubheading(): string
    {
        $torneo = $this->torneo();

        $medidos = app(DuracionesAprendidas::class)->estimador()->partidosMedidos();

        return 'Calentamiento en pista: '.$torneo->margen_entre_partidos_minutos.' min · descanso mínimo: '.$torneo->descanso_minimo_minutos.' min · '
            .$torneo->torneoPistas()->count().' pistas · '
            .($medidos > 0 ? "duraciones aprendidas de {$medidos} partidos jugados" : 'duraciones estimadas (aún no hay partidos medidos)');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('calcular')
                ->label(fn (): string => $this->propuesta === null ? 'Calcular' : 'Volver a calcular')
                ->icon(Heroicon::OutlinedSparkles)
                ->visible(fn (): bool => Gate::allows('update', $this->torneo()))
                ->modalHeading('Calcular los horarios')
                ->modalDescription('Se calcula el plan y se enseña aquí, sin guardar nada. Cada partido reserva su pista el calentamiento más su duración estimada y el siguiente entra en cuanto acaba.')
                ->modalSubmitActionLabel('Calcular')
                ->schema([
                    Toggle::make('rehacer')->label('Rehacer también los ya programados')->default(false)
                        ->helperText('Los que no han empezado se vuelven a colocar desde ahora. Desmarcado, solo se colocan los que no tienen hora.'),
                ])
                ->action(function (array $data): void {
                    $propuesta = app(PlanificarHorarios::class)->proponer($this->torneo(), (bool) ($data['rehacer'] ?? false));
                    $this->propuesta = $propuesta->toArray();

                    $plan = $propuesta->plan;
                    $colocados = count($plan->colocados);
                    if ($colocados === 0 && $plan->conflictos === []) {
                        Avisos::aviso('No hay partidos que colocar');
                    } else {
                        Avisos::hecho('Plan calculado: revísalo y aplícalo', "{$colocados} partidos colocados".($plan->conflictos !== [] ? ', '.count($plan->conflictos).' sin sitio' : '').'.');
                    }
                }),
            Action::make('recalcular')
                ->label('Recalcular horas')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->visible(fn (): bool => $this->propuesta === null && HorasEnDirecto::activo($this->torneo()) && Gate::allows('update', $this->torneo()))
                ->action(function (): void {
                    $cambios = app(HorasEnDirecto::class)->recalcular($this->torneo());
                    Avisos::hecho($cambios === [] ? 'Las horas ya estaban al día' : count($cambios).' partidos con nueva hora estimada');
                }),
            Action::make('aplicar')
                ->label('Aplicar')
                ->icon(Heroicon::OutlinedCheck)
                ->color('success')
                ->visible(fn (): bool => $this->propuesta !== null && ($this->propuesta['colocados'] ?? []) !== [] && Gate::allows('update', $this->torneo()))
                ->requiresConfirmation()
                ->modalHeading('Aplicar el plan')
                ->modalDescription('Los partidos reciben su pista y su hora. Los jugadores lo ven cuando los horarios están publicados.')
                ->modalSubmitActionLabel('Aplicar')
                ->action(function (): void {
                    $propuesta = PropuestaDeHorarios::desdeArray((array) $this->propuesta);

                    try {
                        app(PlanificarHorarios::class)->aplicar($this->torneo(), $propuesta, $this->usuario());
                    } catch (ErrorDeCompeticion $e) {
                        Avisos::error('No se ha aplicado', [$e->getMessage()]);

                        return;
                    }

                    $this->propuesta = null;
                    Avisos::hecho(count($propuesta->plan->colocados).' partidos con hora y pista', self::resumen($propuesta));
                }),
            Action::make('descartar')
                ->label('Descartar')
                ->icon(Heroicon::OutlinedXMark)
                ->color('gray')
                ->visible(fn (): bool => $this->propuesta !== null)
                ->action(fn () => $this->propuesta = null),
            Action::make('partidos')
                ->label('Partidos')
                ->icon(Heroicon::OutlinedTableCells)
                ->color('gray')
                ->url(fn (): string => PartidosDelTorneo::getUrl(['record' => $this->torneo()])),
            Action::make('torneo')
                ->label('Volver al torneo')
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('gray')
                ->url(fn (): string => TorneoResource::getUrl('edit', ['record' => $this->torneo()])),
        ];
    }

    /** «Termina el sábado 14 a las 19:40 · pistas ocupadas al 96 % · 25 min de pista parada.» */
    public static function resumen(PropuestaDeHorarios $propuesta): ?string
    {
        $plan = $propuesta->plan;

        if ($plan->fin === null) {
            return null;
        }

        $fin = CarbonImmutable::instance($plan->fin);

        return 'Termina el '.$fin->translatedFormat('l j').' a las '.$fin->format('H:i').' · pistas ocupadas al '.$plan->ocupacion.' % · '.$plan->minutosMuertos.' min de pista parada.';
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $propuesta = $this->propuesta !== null ? PropuestaDeHorarios::desdeArray($this->propuesta) : null;

        $directo = $propuesta === null && HorasEnDirecto::activo($this->torneo());
        $sugerencia = $directo ? app(HorasEnDirecto::class)->sugerencia($this->torneo()) : null;

        return [
            'directo' => $directo ? app(HorasEnDirecto::class)->estado($this->torneo()) : null,
            'sugerencia' => $sugerencia !== null && ! in_array($sugerencia->clave(), $this->ignoradas, true) ? $this->describir($sugerencia) : null,
            'retrasoMinimo' => HorasEnDirecto::RETRASO_MINUTOS,
            'esPropuesta' => $propuesta !== null,
            'resumen' => $propuesta !== null ? self::resumen($propuesta) : null,
            'dias' => $this->dias($propuesta),
            'conflictos' => $propuesta !== null ? $this->conflictos($propuesta) : [],
            'categorias' => $this->torneo()->categorias()->orderBy('orden')->get(['id', 'nombre'])->values()
                ->map(fn ($categoria, int $i): array => ['nombre' => (string) $categoria->nombre, 'color' => self::COLORES[$i % count(self::COLORES)]])->all(),
        ];
    }

    /**
     * Cada día con sus pistas y lo que ocupa cada una: lo guardado y, si
     * hay una propuesta, lo propuesto en lugar de lo que se recoloca.
     *
     * @return list<array{titulo: string, desde: int, minutos: int, base: int, horas: list<array{etiqueta: string, izquierda: float}>, pistas: list<array{id: int, nombre: string, bloques: list<array{partido: int|null, movible: bool, tercero: bool, izquierda: float, ancho: float, calentamiento: float, hora: string, texto: string, detalle: string, color: string, nuevo: bool}>}>}>
     */
    private function dias(?PropuestaDeHorarios $propuesta): array
    {
        $torneo = $this->torneo();
        $calentamiento = $torneo->margen_entre_partidos_minutos;
        $seRecolocan = $propuesta->partidos ?? [];

        /** @var list<array{partido: int|null, pista: int, inicio: CarbonImmutable, fin: CarbonImmutable, nuevo: bool}> $ocupaciones */
        $ocupaciones = [];

        foreach (Franja::query()->where('torneo_id', $torneo->id)->with('partido')->get() as $franja) {
            $partido = $franja->partido;
            if ($partido !== null && in_array($partido->id, $seRecolocan, true)) {
                continue;
            }
            if ($partido === null && ! $franja->bloqueada) {
                continue;
            }
            $ocupaciones[] = ['partido' => $partido?->id, 'pista' => $franja->pista_id, 'inicio' => CarbonImmutable::instance($franja->inicio), 'fin' => CarbonImmutable::instance($franja->fin), 'nuevo' => false];
        }

        foreach ($propuesta?->plan->colocados ?? [] as $colocado) {
            $ocupaciones[] = ['partido' => $colocado->partido, 'pista' => $colocado->pista, 'inicio' => CarbonImmutable::instance($colocado->inicio), 'fin' => CarbonImmutable::instance($colocado->fin), 'nuevo' => true];
        }

        $partidos = Partido::query()
            ->whereIn('id', array_filter(array_column($ocupaciones, 'partido')))
            ->with(['local.participanteJugadores', 'visitante.participanteJugadores', 'fase.categoria'])
            ->get()
            ->keyBy('id');
        $sePuedeMover = Gate::allows('update', $torneo);
        $terceros = self::tercerosSeguidos($ocupaciones, $partidos->all(), $calentamiento);

        $colores = [];
        foreach ($torneo->categorias()->orderBy('orden')->pluck('id')->values() as $i => $categoria) {
            $colores[(int) $categoria] = self::COLORES[$i % count(self::COLORES)];
        }

        $pistas = $torneo->torneoPistas()->with('pista')->orderBy('orden')->get();

        // Los límites de cada día: lo que abren las pistas, ampliado si algo se sale.
        /** @var array<string, array{0: CarbonImmutable, 1: CarbonImmutable}> $limites */
        $limites = [];
        foreach ($pistas as $torneoPista) {
            /** @var TorneoPista $torneoPista */
            try {
                $tramos = DisponibilidadDePista::desdeArray($torneoPista->disponibilidad)->tramos;
            } catch (ConfiguracionInvalida) {
                $tramos = [];
            }
            foreach ($tramos as $tramo) {
                $desde = CarbonImmutable::parse($tramo->fecha.' '.$tramo->desde);
                $hasta = CarbonImmutable::parse($tramo->fecha.' '.$tramo->hasta);
                $limites[$tramo->fecha] = isset($limites[$tramo->fecha]) ? [$limites[$tramo->fecha][0]->min($desde), $limites[$tramo->fecha][1]->max($hasta)] : [$desde, $hasta];
            }
        }
        foreach ($ocupaciones as $o) {
            $fecha = $o['inicio']->toDateString();
            $limites[$fecha] = isset($limites[$fecha]) ? [$limites[$fecha][0]->min($o['inicio']), $limites[$fecha][1]->max($o['fin'])] : [$o['inicio'], $o['fin']];
        }
        ksort($limites);

        $dias = [];
        foreach ($limites as $fecha => [$desde, $hasta]) {
            $total = max(1, $desde->diffInMinutes($hasta));
            $posicion = fn (CarbonImmutable $momento): float => round(100 * $desde->diffInMinutes($momento) / $total, 3);

            $horas = [];
            for ($hora = $desde->ceilHour(); $hora->lte($hasta); $hora = $hora->addHour()) {
                $horas[] = ['etiqueta' => $hora->format('H:i'), 'izquierda' => $posicion($hora)];
            }

            $filas = [];
            foreach ($pistas as $torneoPista) {
                $bloques = [];
                $dePista = array_filter($ocupaciones, fn (array $o): bool => $o['pista'] === $torneoPista->pista_id && $o['inicio']->toDateString() === $fecha);
                usort($dePista, fn (array $a, array $b): int => $a['inicio'] <=> $b['inicio']);

                foreach ($dePista as $o) {
                    $partido = $o['partido'] !== null ? $partidos->get($o['partido']) : null;
                    $minutos = max(1, $o['inicio']->diffInMinutes($o['fin']));
                    $texto = $partido !== null ? Marcador::lado($partido, true).' – '.Marcador::lado($partido, false) : 'Bloqueada';
                    $categoria = $partido?->fase->categoria;

                    $bloques[] = [
                        'partido' => $partido?->id,
                        // En una propuesta se mueve lo propuesto; en lo guardado, lo que no se ha jugado ni está en juego.
                        'movible' => $sePuedeMover && $partido !== null && ($propuesta !== null ? $o['nuevo'] : self::movible($partido)),
                        'izquierda' => $posicion($o['inicio']),
                        'ancho' => round(100 * $minutos / $total, 3),
                        'calentamiento' => $partido !== null ? round(100 * min($calentamiento, $minutos) / $minutos, 3) : 0.0,
                        'hora' => $o['inicio']->format('H:i'),
                        'texto' => $texto,
                        'detalle' => $o['inicio']->format('H:i').'–'.$o['fin']->format('H:i').($categoria !== null ? ' · '.$categoria->nombre : '').($partido !== null ? ' · P'.$partido->id : '').' · '.$texto
                            .($partido !== null && isset($terceros[$partido->id]) ? ' · ¡Alguien juega su tercer partido seguido!' : ''),
                        'color' => $categoria !== null ? ($colores[$categoria->id] ?? '#6d7a84') : '#c4c9ce',
                        'nuevo' => $o['nuevo'],
                        'tercero' => $partido !== null && isset($terceros[$partido->id]),
                    ];
                }

                $filas[] = ['id' => $torneoPista->pista_id, 'nombre' => $torneoPista->pista->nombre.($torneoPista->pista->exterior ? ' · exterior' : ''), 'bloques' => $bloques];
            }

            $dias[] = ['titulo' => ucfirst($desde->translatedFormat('l j \d\e F')), 'desde' => $desde->getTimestamp(), 'minutos' => (int) $total, 'base' => $desde->hour * 60 + $desde->minute, 'horas' => $horas, 'pistas' => $filas];
        }

        return $dias;
    }

    /**
     * Arrastrado a una pista y una hora: se pega al anterior si queda
     * cerca, empuja a los de detrás y en su pista suben los que iban
     * pegados. En una propuesta, solo cambia la propuesta.
     */
    public function moverPartido(int $partido, int $pista, int $inicio): void
    {
        $this->cambiarHorario(function (array $ocupaciones, int $calentamiento) use ($partido, $pista, $inicio): array {
            // Uno que no cabía en la propuesta entra desde fuera del horario.
            if (! in_array($partido, array_column($ocupaciones, 'id'), true)) {
                $ocupaciones[] = $this->sinSitio($partido, $calentamiento);
            }

            return app(MoverEnElHorario::class)->mover($ocupaciones, $partido, $pista, CarbonImmutable::now()->setTimestamp($inicio)->roundMinutes(5)->getTimestamp(), $calentamiento);
        });
    }

    /** Soltado sobre otro partido: se intercambian. */
    public function intercambiarPartidos(int $partido, int $otro): void
    {
        $this->cambiarHorario(fn (array $ocupaciones, int $calentamiento): array => app(MoverEnElHorario::class)->intercambiar($ocupaciones, $partido, $otro, $calentamiento));
    }

    /**
     * Un partido que se quedó sin sitio en la propuesta, listo para
     * colocarlo a mano (fuera de toda pista hasta que se suelta).
     *
     * @return array{id: int, pista: int, inicio: int, fin: int, movible: bool, jugadores: list<int>}
     *
     * @throws OperacionNoPermitida
     */
    private function sinSitio(int $id, int $calentamiento): array
    {
        $propuesta = $this->propuesta !== null ? PropuestaDeHorarios::desdeArray($this->propuesta) : null;
        $sinSitio = array_map(fn ($c): int => $c->partido, $propuesta->plan->conflictos ?? []);
        $partido = in_array($id, $sinSitio, true)
            ? Partido::query()->with(['local.participanteJugadores', 'visitante.participanteJugadores', 'fase.categoria'])->find($id)
            : null;
        if ($partido === null || $partido->local === null || $partido->visitante === null) {
            throw new OperacionNoPermitida('Ese partido no está en el horario.');
        }

        return [
            'id' => $partido->id,
            'pista' => 0,
            'inicio' => 0,
            'fin' => ($calentamiento + app(DuracionesAprendidas::class)->minutosDe($partido)) * 60,
            'movible' => true,
            'jugadores' => array_values(array_unique([...$partido->local->idsDeJugadores(), ...$partido->visitante->idsDeJugadores()])),
        ];
    }

    /**
     * @param  callable(list<array{id: int, pista: int, inicio: int, fin: int, movible: bool, jugadores: list<int>}>, int): array{cambios: array<int, array{pista: int, inicio: int, fin: int}>, avisos: list<array{partido: int, tipo: string}>}  $cambio
     */
    private function cambiarHorario(callable $cambio): void
    {
        abort_unless(Gate::allows('update', $this->torneo()), 403);
        $torneo = $this->torneo();
        $propuesta = $this->propuesta !== null ? PropuestaDeHorarios::desdeArray($this->propuesta) : null;

        try {
            $resultado = $cambio($this->ocupaciones($propuesta), $torneo->margen_entre_partidos_minutos);
        } catch (ErrorDeCompeticion $e) {
            Avisos::error('No se ha movido', [$e->getMessage()]);

            return;
        }

        if ($resultado['cambios'] === []) {
            return;
        }

        if ($this->propuesta !== null) {
            $colocados = [];
            $estaban = [];
            foreach ((array) ($this->propuesta['colocados'] ?? []) as $c) {
                $c = array_values((array) $c);
                $estaban[] = (int) $c[0];
                $nuevo = $resultado['cambios'][(int) $c[0]] ?? null;
                $colocados[] = $nuevo !== null ? [(int) $c[0], $nuevo['pista'], $nuevo['inicio'], $nuevo['fin']] : $c;
            }
            // Los que no tenían sitio y se han soltado en el horario: dejan de ser conflicto.
            foreach ($resultado['cambios'] as $id => $nuevo) {
                if (! in_array($id, $estaban, true)) {
                    $colocados[] = [$id, $nuevo['pista'], $nuevo['inicio'], $nuevo['fin']];
                    $this->propuesta['conflictos'] = array_values(array_filter((array) ($this->propuesta['conflictos'] ?? []), fn (mixed $c): bool => (int) (array_values((array) $c)[0] ?? 0) !== $id));
                }
            }
            $this->propuesta['colocados'] = $colocados;
            $this->propuesta['fin'] = max(array_map(fn (array $c): int => (int) $c[3], $colocados));
        } else {
            try {
                $this->guardarCambios($resultado['cambios']);
            } catch (ErrorDeCompeticion $e) {
                Avisos::error('No se ha movido', [$e->getMessage()]);

                return;
            }
        }

        $avisos = [];
        // Tres seguidos se ve en el propio partido (con «!»); aquí solo se avisa de lo que no puede ser.
        $resultado['avisos'] = array_values(array_filter($resultado['avisos'], fn (array $a): bool => $a['tipo'] === 'a_la_vez'));
        if ($resultado['avisos'] !== []) {
            $nombres = Partido::query()->with(['local', 'visitante'])->whereIn('id', array_column($resultado['avisos'], 'partido'))->get()->keyBy('id');
            foreach ($resultado['avisos'] as $aviso) {
                $partido = $nombres->get($aviso['partido']);
                $quien = $partido !== null ? Marcador::lado($partido, true).' – '.Marcador::lado($partido, false) : 'P'.$aviso['partido'];
                $avisos[] = $quien.': alguien juega a la vez en otra pista.';
            }
        }

        $movidos = count($resultado['cambios']);
        if ($avisos !== []) {
            Avisos::aviso('Movido, pero revísalo', $avisos);
        } else {
            Avisos::hecho($movidos === 1 ? 'Partido movido' : "Movido: cambian {$movidos} partidos", $this->propuesta !== null ? 'En la propuesta: se guarda al aplicarla.' : null);
        }
    }

    /**
     * Lo que ocupa las pistas, para moverlo. Con una propuesta, solo se
     * mueve lo propuesto; lo guardado no se toca.
     *
     * @return list<array{id: int, pista: int, inicio: int, fin: int, movible: bool, jugadores: list<int>}>
     */
    private function ocupaciones(?PropuestaDeHorarios $propuesta): array
    {
        $torneo = $this->torneo();
        $seRecolocan = $propuesta->partidos ?? [];
        $jugadoresDe = fn (?Partido $p): array => $p === null ? [] : array_values(array_unique([...($p->local?->idsDeJugadores() ?? []), ...($p->visitante?->idsDeJugadores() ?? [])]));

        $ocupaciones = [];
        $franjas = Franja::query()->where('torneo_id', $torneo->id)
            ->with(['partido.local.participanteJugadores', 'partido.visitante.participanteJugadores', 'partido.local.jugadores', 'partido.visitante.jugadores'])
            ->get();
        foreach ($franjas as $franja) {
            $partido = $franja->partido;
            if (($partido === null && ! $franja->bloqueada) || ($partido !== null && in_array($partido->id, $seRecolocan, true))) {
                continue;
            }
            $ocupaciones[] = [
                'id' => $partido !== null ? $partido->id : -$franja->id,
                'pista' => $franja->pista_id,
                'inicio' => $franja->inicio->getTimestamp(),
                'fin' => $franja->fin->getTimestamp(),
                'movible' => $propuesta === null && $partido !== null && self::movible($partido),
                'jugadores' => $jugadoresDe($partido),
            ];
        }

        if ($propuesta !== null) {
            $partidos = Partido::query()->whereIn('id', array_map(fn ($c): int => $c->partido, $propuesta->plan->colocados))
                ->with(['local.participanteJugadores', 'visitante.participanteJugadores', 'local.jugadores', 'visitante.jugadores'])->get()->keyBy('id');
            foreach ($propuesta->plan->colocados as $c) {
                $ocupaciones[] = ['id' => $c->partido, 'pista' => $c->pista, 'inicio' => $c->inicio->getTimestamp(), 'fin' => $c->fin->getTimestamp(), 'movible' => true, 'jugadores' => $jugadoresDe($partidos->get($c->partido))];
            }
        }

        return $ocupaciones;
    }

    /**
     * Guarda las horas y pistas nuevas de lo movido.
     *
     * @param  array<int, array{pista: int, inicio: int, fin: int}>  $cambios
     *
     * @throws OperacionNoPermitida
     */
    private function guardarCambios(array $cambios): void
    {
        $torneo = $this->torneo();
        $usuario = $this->usuario();

        DB::transaction(function () use ($torneo, $usuario, $cambios): void {
            $antes = [];
            foreach (Partido::query()->with('franja')->whereIn('id', array_keys($cambios))->get() as $partido) {
                $franja = $partido->franja;
                if ($franja === null || ! self::movible($partido)) {
                    throw new OperacionNoPermitida('El horario ha cambiado mientras tanto: recarga la página.');
                }
                $antes[$partido->id] = [$franja->pista_id, $franja->inicio->format('Y-m-d H:i')];
                $nuevo = $cambios[$partido->id];
                $inicio = CarbonImmutable::now()->setTimestamp($nuevo['inicio']);
                $franja->update(['pista_id' => $nuevo['pista'], 'inicio' => $inicio, 'fin' => CarbonImmutable::now()->setTimestamp($nuevo['fin']), 'inicio_previsto' => $inicio]);
                $partido->forceFill(['pista_id' => $nuevo['pista'], 'aviso_siguientes_en' => null, 'aviso_calentar_en' => null])->save();
            }

            RegistroDeCambio::anotar('mover_en_horario', $torneo, $usuario, ['partidos' => $antes], ['partidos' => array_map(fn (array $c): array => [$c['pista'], date('Y-m-d H:i', $c['inicio'])], $cambios)]);
        });
    }

    /**
     * Los partidos que son el tercero (o más) seguido de alguno de sus
     * jugadores: con menos de 20 minutos sin jugar entre uno y otro.
     *
     * @param  list<array{partido: int|null, pista: int, inicio: CarbonImmutable, fin: CarbonImmutable, nuevo: bool}>  $ocupaciones
     * @param  array<int, Partido>  $partidos
     * @return array<int, true>
     */
    private static function tercerosSeguidos(array $ocupaciones, array $partidos, int $calentamientoMinutos): array
    {
        $porJugador = [];
        foreach ($ocupaciones as $o) {
            $partido = $o['partido'] !== null ? ($partidos[$o['partido']] ?? null) : null;
            if ($partido === null) {
                continue;
            }
            // Quien viene de jugar no calienta: cuenta el juego.
            $juega = $o['inicio']->addMinutes($calentamientoMinutos)->min($o['fin'])->getTimestamp();
            foreach ([...($partido->local?->idsDeJugadores() ?? []), ...($partido->visitante?->idsDeJugadores() ?? [])] as $jugador) {
                $porJugador[$jugador][] = [$juega, $o['fin']->getTimestamp(), $partido->id];
            }
        }

        $terceros = [];
        foreach ($porJugador as $juegos) {
            usort($juegos, fn (array $a, array $b): int => $a[0] <=> $b[0]);
            $racha = 1;
            for ($i = 1; $i < count($juegos); $i++) {
                $racha = $juegos[$i][0] - $juegos[$i - 1][1] < PlanificadorContinuo::MINUTOS_PARA_DESCANSAR * 60 ? $racha + 1 : 1;
                if ($racha >= 3) {
                    $terceros[$juegos[$i][2]] = true;
                }
            }
        }

        return $terceros;
    }

    /** Un partido que todavía no se ha jugado ni está en juego. */
    private static function movible(Partido $partido): bool
    {
        return in_array($partido->estado, [EstadoPartido::Pendiente, EstadoPartido::Programado], true);
    }

    public function aplicarSugerencia(string $clave): void
    {
        abort_unless(Gate::allows('update', $this->torneo()), 403);

        try {
            app(HorasEnDirecto::class)->aplicar($this->torneo(), $clave, $this->usuario());
        } catch (ErrorDeCompeticion $e) {
            Avisos::error('No se ha recolocado', [$e->getMessage()]);

            return;
        }

        Avisos::hecho('Partido recolocado', 'Se ha avisado a sus parejas y a las que cambian de hora.');
    }

    public function ignorarSugerencia(string $clave): void
    {
        $this->ignoradas[] = $clave;
    }

    /** @return array{clave: string, texto: string, ganancia: string} */
    private function describir(SugerenciaDeRecolocacion $sugerencia): array
    {
        $partido = Partido::query()->with(['local', 'visitante', 'franja', 'fase.categoria'])->find($sugerencia->partido);
        $pistas = Pista::query()->whereIn('id', [$sugerencia->desdePista, $sugerencia->haciaPista])->pluck('nombre', 'id');
        $quien = $partido !== null ? Marcador::lado($partido, true).' – '.Marcador::lado($partido, false).' ('.$partido->fase->categoria->nombre.')' : 'P'.$sugerencia->partido;

        return [
            'clave' => $sugerencia->clave(),
            'texto' => $pistas->get($sugerencia->desdePista).' va '.$sugerencia->retrasoDeLaPista.' min tarde. Propuesta: pasar '.$quien
                .($partido?->franja !== null ? ' (ahora hacia las '.$partido->franja->inicio->format('H:i').')' : '')
                .' a '.$pistas->get($sugerencia->haciaPista).' hacia las '.now()->setTimestamp($sugerencia->inicio)->format('H:i').'.',
            'ganancia' => 'Ahorra '.$sugerencia->ganaRetraso.' min de espera entre todas las parejas'.($sugerencia->ganaFinal > 0 ? ' y adelanta el final '.$sugerencia->ganaFinal.' min' : '').'.',
        ];
    }

    /** @return list<array{partido: int, texto: string, movible: bool}> */
    private function conflictos(PropuestaDeHorarios $propuesta): array
    {
        $partidos = Partido::query()
            ->whereIn('id', array_map(fn ($c): int => $c->partido, $propuesta->plan->conflictos))
            ->with(['local', 'visitante', 'fase.categoria'])
            ->get()
            ->keyBy('id');

        $lineas = [];
        foreach ($propuesta->plan->conflictos as $conflicto) {
            $partido = $partidos->get($conflicto->partido);
            $lineas[] = [
                'partido' => $conflicto->partido,
                'texto' => ($partido !== null
                    ? $partido->fase->categoria->nombre.' · '.Marcador::lado($partido, true).' – '.Marcador::lado($partido, false)
                    : 'P'.$conflicto->partido).': '.$conflicto->detalle,
                // Con sus dos parejas se puede soltar a mano en el horario.
                'movible' => $partido !== null && $partido->local_id !== null && $partido->visitante_id !== null && Gate::allows('update', $this->torneo()),
            ];
        }

        return $lineas;
    }

    private function usuario(): User
    {
        $usuario = Filament::auth()->user();
        assert($usuario instanceof User);

        return $usuario;
    }

    private function torneo(): Torneo
    {
        $torneo = $this->getRecord();
        assert($torneo instanceof Torneo);

        return $torneo;
    }
}
