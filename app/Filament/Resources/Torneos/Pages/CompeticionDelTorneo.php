<?php

namespace App\Filament\Resources\Torneos\Pages;

use App\Actions\Competicion\CerrarFase;
use App\Competicion\Errores\ErrorDeCompeticion;
use App\Enums\EstadoFase;
use App\Filament\Avisos;
use App\Filament\Competicion\AccionesDePartido;
use App\Filament\Competicion\VistaDeCompeticion;
use App\Filament\Resources\Categorias\CategoriaResource;
use App\Filament\Resources\Torneos\Pages\Concerns\BarraDelTorneo;
use App\Filament\Resources\Torneos\TorneoResource;
use App\Models\Categoria;
use App\Models\Fase;
use App\Models\Partido;
use App\Models\Torneo;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;

/**
 * La competición del torneo de un vistazo, categoría a categoría: los
 * grupos con su clasificación, el cruce previsto y sus partidos; el
 * cuadro dibujado por rondas; y, en cada partido, anotar, validar o
 * anular el resultado sin salir de aquí. Es la página del día del torneo.
 */
class CompeticionDelTorneo extends Page
{
    use BarraDelTorneo;
    use InteractsWithRecord;

    protected static string $resource = TorneoResource::class;

    protected static ?string $breadcrumb = 'Competición';

    protected string $view = 'filament.panel.competicion.torneo';

    #[Url(as: 'categoria')]
    public ?int $categoriaId = null;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        abort_unless(Gate::allows('view', $this->torneo()), 403);

        if ($this->categoriaId === null || ! $this->categorias()->contains('id', $this->categoriaId)) {
            $this->categoriaId = $this->categorias()->first()?->id;
        }
    }

    public function getTitle(): string|Htmlable
    {
        return 'Competición · '.$this->torneo()->nombre;
    }

    public function elegirCategoria(int $categoria): void
    {
        abort_unless($this->categorias()->contains('id', $categoria), 404);
        $this->categoriaId = $categoria;
    }

    protected function getHeaderActions(): array
    {
        return $this->barraDelTorneo('competicion');
    }

    public function resultadoAction(): Action
    {
        return $this->sobreUnPartido(AccionesDePartido::resultado());
    }

    public function validarAction(): Action
    {
        return $this->sobreUnPartido(AccionesDePartido::validar());
    }

    public function anularAction(): Action
    {
        return $this->sobreUnPartido(AccionesDePartido::anular());
    }

    public function programarAction(): Action
    {
        return $this->sobreUnPartido(AccionesDePartido::programar());
    }

    public function cerrarFaseAction(): Action
    {
        return Action::make('cerrarFase')
            ->label('Cerrar fase')
            ->icon(Heroicon::OutlinedLockClosed)
            ->color('success')
            ->record(fn (array $arguments): ?Fase => $this->fase($arguments))
            ->hidden(fn (array $arguments): bool => $this->fase($arguments) === null)
            // Cerrar una fase (y armar el cuadro) es de la organización, no del árbitro.
            ->visible(fn (Fase $record): bool => in_array($record->estado, [EstadoFase::Preparada, EstadoFase::EnJuego], true) && Gate::allows('update', $this->torneo()))
            ->disabled(fn (Fase $record): bool => ! self::cerrable($record))
            ->tooltip(fn (Fase $record): ?string => self::cerrable($record) ? null : 'Hace falta que todos los partidos tengan resultado.')
            ->requiresConfirmation()
            ->modalHeading(fn (Fase $record): string => 'Cerrar '.$record->nombre)
            ->modalDescription(fn (Fase $record): string => $record->tipo->esDeGrupos()
                ? 'Hace falta que todos los partidos tengan resultado. Se deciden los clasificados y, si la fase siguiente es un cuadro, se construye con ellos.'
                : 'Hace falta que todos los partidos tengan resultado. La fase queda cerrada.')
            ->schema(fn (Fase $record): array => $record->tipo->esDeGrupos() ? [
                Toggle::make('avisar')->label('Avisar a cada pareja clasificada de su cruce')->default(false)
                    ->helperText('Por correo y en su portal. Desmarcado, no se avisa a nadie.'),
            ] : [])
            ->action(function (Fase $record, array $data, Action $action): void {
                try {
                    $clasificados = app(CerrarFase::class)->handle($record, $this->usuario(), avisar: (bool) ($data['avisar'] ?? false));
                } catch (ErrorDeCompeticion $e) {
                    Avisos::error('No se ha podido cerrar la fase', [$e->getMessage()]);
                    $action->halt();

                    return;
                }

                Avisos::hecho('Fase cerrada', $clasificados === [] ? null : count($clasificados).' clasificados pasan a la fase siguiente.');
            });
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $categoria = $this->categoriaId === null ? null : $this->categorias()->firstWhere('id', $this->categoriaId);

        return [
            'torneo' => $this->torneo(),
            'categorias' => $this->categorias(),
            'categoria' => $categoria,
            'fases' => $categoria === null ? [] : app(VistaDeCompeticion::class)->deCategoria($categoria),
            // Configurar la categoría es de la organización: el árbitro no tiene ese enlace.
            'configurar' => $categoria === null || TorneoResource::esArbitro() ? null : CategoriaResource::getUrl('edit', ['record' => $categoria]),
        ];
    }

    /** @return Collection<int, Categoria> */
    private function categorias(): Collection
    {
        return $this->torneo()->categorias()->orderBy('orden')->get();
    }

    /** La acción de una tabla de partidos, aquí sobre el partido que diga el argumento. */
    private function sobreUnPartido(Action $accion): Action
    {
        return $accion
            ->record(fn (array $arguments): ?Partido => $this->partido($arguments))
            ->hidden(fn (array $arguments): bool => $this->partido($arguments) === null)
            ->link()->size('sm');
    }

    /** Una fase se cierra cuando tiene partidos y todos tienen resultado. */
    public static function cerrable(Fase $fase): bool
    {
        return $fase->estado !== EstadoFase::Cerrada
            && $fase->partidos()->exists()
            && $fase->partidos()->whereNotIn('estado', ['jugado', 'walkover'])->doesntExist();
    }

    /** @param  array<string, mixed>  $arguments */
    private function partido(array $arguments): ?Partido
    {
        return Partido::query()->whereKey((int) ($arguments['partido'] ?? 0))
            ->whereHas('fase.categoria', fn (Builder $q) => $q->where('torneo_id', $this->torneo()->id))
            ->with(['fase.categoria.torneo', 'local', 'visitante', 'sets'])
            ->first();
    }

    /** @param  array<string, mixed>  $arguments */
    private function fase(array $arguments): ?Fase
    {
        return Fase::query()->whereKey((int) ($arguments['fase'] ?? 0))
            ->whereHas('categoria', fn (Builder $q) => $q->where('torneo_id', $this->torneo()->id))
            ->first();
    }

    private function usuario(): User
    {
        $usuario = Filament::auth()->user();
        assert($usuario instanceof User);

        return $usuario;
    }

    public function torneo(): Torneo
    {
        $torneo = $this->getRecord();
        assert($torneo instanceof Torneo);

        return $torneo;
    }
}
