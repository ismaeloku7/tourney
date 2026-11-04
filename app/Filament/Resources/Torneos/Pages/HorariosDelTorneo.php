<?php

namespace App\Filament\Resources\Torneos\Pages;

use App\Actions\Competicion\PlanificarHorarios;
use App\Competicion\Datos\DisponibilidadDePista;
use App\Competicion\Datos\PropuestaDeHorarios;
use App\Competicion\Errores\ConfiguracionInvalida;
use App\Competicion\Errores\ErrorDeCompeticion;
use App\Filament\Avisos;
use App\Filament\Competicion\Marcador;
use App\Filament\Resources\Torneos\TorneoResource;
use App\Models\Franja;
use App\Models\Partido;
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

        return 'Calentamiento en pista: '.$torneo->margen_entre_partidos_minutos.' min · descanso mínimo: '.$torneo->descanso_minimo_minutos.' min · '
            .$torneo->torneoPistas()->count().' pistas';
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

        return [
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
     * @return list<array{titulo: string, horas: list<array{etiqueta: string, izquierda: float}>, pistas: list<array{nombre: string, bloques: list<array{izquierda: float, ancho: float, calentamiento: float, hora: string, texto: string, detalle: string, color: string, nuevo: bool}>}>}>
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
            ->with(['local', 'visitante', 'fase.categoria'])
            ->get()
            ->keyBy('id');

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
                        'izquierda' => $posicion($o['inicio']),
                        'ancho' => round(100 * $minutos / $total, 3),
                        'calentamiento' => $partido !== null ? round(100 * min($calentamiento, $minutos) / $minutos, 3) : 0.0,
                        'hora' => $o['inicio']->format('H:i'),
                        'texto' => $texto,
                        'detalle' => $o['inicio']->format('H:i').'–'.$o['fin']->format('H:i').($categoria !== null ? ' · '.$categoria->nombre : '').($partido !== null ? ' · P'.$partido->id : '').' · '.$texto,
                        'color' => $categoria !== null ? ($colores[$categoria->id] ?? '#6d7a84') : '#c4c9ce',
                        'nuevo' => $o['nuevo'],
                    ];
                }

                $filas[] = ['nombre' => $torneoPista->pista->nombre, 'bloques' => $bloques];
            }

            $dias[] = ['titulo' => ucfirst($desde->translatedFormat('l j \d\e F')), 'horas' => $horas, 'pistas' => $filas];
        }

        return $dias;
    }

    /** @return list<string> */
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
            $lineas[] = ($partido !== null
                ? $partido->fase->categoria->nombre.' · '.Marcador::lado($partido, true).' – '.Marcador::lado($partido, false)
                : 'P'.$conflicto->partido).': '.$conflicto->detalle;
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
