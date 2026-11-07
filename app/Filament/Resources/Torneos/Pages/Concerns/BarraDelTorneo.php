<?php

namespace App\Filament\Resources\Torneos\Pages\Concerns;

use App\Actions\Avisos\EnviarComunicacion;
use App\Actions\Competicion\CambiarEstadoDelTorneo;
use App\Actions\Inscripciones\ImportarParejas;
use App\Competicion\Errores\ErrorDeCompeticion;
use App\Enums\EstadoTorneo;
use App\Exportaciones\AccionesDeExportacion;
use App\Filament\Avisos;
use App\Filament\Resources\Torneos\Pages\ViewTorneo;
use App\Filament\Resources\Torneos\TorneoResource;
use App\Models\Categoria;
use App\Models\Fase;
use App\Models\Torneo;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * La barra de arriba de todas las páginas de un torneo, siempre la misma
 * y en el mismo orden: la ficha, editarla, la competición, los partidos y
 * los horarios (resaltada la página en la que se está), «Más» (importar,
 * qué se ve en la web, avisos, exportar, borrar) y el estado. Lo propio
 * de cada página va en la página, no aquí.
 */
trait BarraDelTorneo
{
    /** La pestaña «Parejas» (su posición en TorneoResource::getRelations). */
    private const PESTANA_PAREJAS = '1';

    abstract private function torneo(): Torneo;

    /**
     * @param  string  $aqui  ficha, editar, competicion, partidos u horarios.
     * @return list<Action|ActionGroup>
     */
    protected function barraDelTorneo(string $aqui): array
    {
        $ir = fn (string $nombre, string $etiqueta, Heroicon $icono, string $pagina): Action => Action::make($nombre)
            ->label($etiqueta)
            ->icon($icono)
            ->color($aqui === $nombre ? 'primary' : 'gray')
            ->url(fn (): string => TorneoResource::getUrl($pagina, ['record' => $this->torneo()]));

        return [
            // Un árbitro solo lleva los partidos: sin ficha, sin «Más» y sin cambiar el estado.
            $ir('ficha', 'Ficha', Heroicon::OutlinedDocumentText, 'view')->hidden(fn (): bool => TorneoResource::esArbitro()),
            $ir('editar', 'Editar', Heroicon::OutlinedPencilSquare, 'edit')->visible(fn (): bool => Gate::allows('update', $this->torneo())),
            $ir('competicion', 'Competición', Heroicon::OutlinedTrophy, 'competicion'),
            $ir('partidos', 'Partidos', Heroicon::OutlinedTableCells, 'partidos'),
            $ir('horarios', 'Horarios', Heroicon::OutlinedCalendarDays, 'horarios'),
            ActionGroup::make([
                Action::make('importar')
                    ->label('Importar parejas')
                    ->icon(Heroicon::OutlinedArrowUpTray)
                    ->color('gray')
                    ->modalHeading('Importar parejas y jugadores')
                    ->modalDescription('Un Excel (.xlsx) o CSV con una fila por pareja: categoría (o nivel), nombre del equipo, jugador 1 y jugador 2 con su correo y su teléfono. Las categorías que no existan se crean; las parejas sin compañero entran pendientes; importar dos veces el mismo archivo no duplica nada.')
                    ->modalSubmitActionLabel('Importar')
                    ->schema([
                        FileUpload::make('archivo')->label('Archivo')->required()
                            ->disk('local')->directory('importaciones')->visibility('private')
                            ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream', 'text/csv', 'text/plain', 'application/vnd.ms-excel'])
                            ->maxSize(5 * 1024),
                        Toggle::make('confirmar')->label('Confirmar las parejas completas')->default(true)
                            ->helperText('Desactivado, entran pendientes y las confirmas tú.'),
                        Toggle::make('avisar')->label('Avisar a los jugadores por correo')->default(false)
                            ->helperText('Les llega «Inscripción confirmada» con la dirección de su portal.'),
                    ])
                    ->action(function (array $data): void {
                        $ruta = (string) $data['archivo'];
                        $disco = Storage::disk('local');

                        $informe = app(ImportarParejas::class)->handle(
                            $this->torneo(),
                            $disco->path($ruta),
                            pathinfo($ruta, PATHINFO_EXTENSION),
                            $this->usuarioDelTorneo(),
                            avisar: (bool) ($data['avisar'] ?? false),
                            confirmar: (bool) ($data['confirmar'] ?? true),
                        );
                        $disco->delete($ruta);

                        $incidencias = [...$informe->errores, ...$informe->avisos];

                        if ($informe->parejas + $informe->sinCompanero + $informe->yaEstaban === 0) {
                            Avisos::error('No se ha importado nada', array_slice($incidencias, 0, 8));

                            return;
                        }

                        Avisos::hecho('Importación hecha', $informe->resumen().' Las tienes en la pestaña «Parejas»; cada categoría, en «Categorías».');
                        if ($incidencias !== []) {
                            Avisos::aviso(count($incidencias).' cosas que revisar (lo demás se ha importado)', [
                                ...array_slice($incidencias, 0, 12),
                                ...(count($incidencias) > 12 ? ['Y '.(count($incidencias) - 12).' más.'] : []),
                            ]);
                        }

                        // Se recarga la ficha para que las categorías y parejas nuevas se vean ya.
                        $this->redirect(ViewTorneo::getUrl(['record' => $this->torneo(), 'relation' => self::PESTANA_PAREJAS]));
                    }),
                Action::make('web')
                    ->label('Qué se ve en la web')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->modalHeading('Qué se ve en la web del torneo')
                    ->modalDescription('Desmarque lo que aún no quiera enseñar: no sale en la web, ni en el tablero, ni en el portal de los jugadores. Usted lo sigue viendo y gestionando aquí. Por defecto se ve todo.')
                    ->modalSubmitActionLabel('Guardar')
                    ->fillForm(fn (): array => [
                        'web_inscritos' => $this->torneo()->web_inscritos,
                        'web_horarios' => $this->torneo()->web_horarios,
                        'fases' => $this->fasesDelTorneo()->where('visible_en_web', true)->modelKeys(),
                    ])
                    ->schema([
                        Toggle::make('web_inscritos')->label('La lista de inscritos'),
                        Toggle::make('web_horarios')->label('Los horarios')
                            ->helperText('Con los horarios ocultos tampoco salen el tablero ni las páginas de las pistas.'),
                        CheckboxList::make('fases')->label('Grupos, cuadros y finales')
                            ->options(fn (): array => $this->fasesDelTorneo()->mapWithKeys(fn (Fase $f): array => [$f->id => $f->categoria->nombre.' · '.$f->nombre])->all())
                            ->bulkToggleable()
                            ->helperText('Cada fase de cada categoría: sus grupos con la clasificación, su cuadro y sus partidos.'),
                    ])
                    ->action(function (array $data): void {
                        $torneo = $this->torneo();
                        $torneo->update(['web_inscritos' => (bool) ($data['web_inscritos'] ?? true), 'web_horarios' => (bool) ($data['web_horarios'] ?? true)]);

                        $visibles = array_map('intval', (array) ($data['fases'] ?? []));
                        foreach ($this->fasesDelTorneo() as $fase) {
                            $fase->update(['visible_en_web' => in_array($fase->id, $visibles, true)]);
                        }

                        Avisos::hecho('Guardado: la web enseña solo lo marcado');
                    }),
                Action::make('aviso')
                    ->label('Enviar aviso')
                    ->icon(Heroicon::OutlinedMegaphone)
                    ->color('gray')
                    ->modalHeading('Aviso a los inscritos')
                    ->modalDescription('Les llega a las parejas inscritas (confirmadas, pendientes y en espera) por los canales que elijas, y queda en su portal.')
                    ->modalSubmitActionLabel('Enviar')
                    ->schema([
                        Select::make('categoria_id')->label('A quién')->placeholder('Todo el torneo')
                            ->options(fn (): array => $this->torneo()->categorias()->pluck('nombre', 'id')->all()),
                        TextInput::make('titulo')->label('Título')->required()->maxLength(120),
                        Textarea::make('cuerpo')->label('Mensaje')->required()->rows(4)->maxLength(2000),
                        CheckboxList::make('canales')->label('Canales')->required()
                            ->options(['correo' => 'Correo', 'push' => 'Push (móvil)'])->default(['correo', 'push'])->columns(2),
                    ])
                    ->action(function (array $data, Action $action): void {
                        $categoria = filled($data['categoria_id'] ?? null) ? Categoria::query()->find((int) $data['categoria_id']) : null;

                        try {
                            $comunicacion = app(EnviarComunicacion::class)->handle($this->torneo(), $categoria, (string) $data['titulo'], (string) $data['cuerpo'], array_values((array) $data['canales']), $this->usuarioDelTorneo());
                        } catch (ErrorDeCompeticion $e) {
                            Avisos::error('No se ha podido enviar', [$e->getMessage()]);
                            $action->halt();

                            return;
                        }

                        Avisos::hecho('Aviso enviado', $comunicacion->destinatarios === 1 ? 'A 1 jugador.' : "A {$comunicacion->destinatarios} jugadores.");
                    }),
                ActionGroup::make([
                    AccionesDeExportacion::pdfDeHorarios(),
                    AccionesDeExportacion::csvDeInscritos(),
                ])->dropdown(false),
                DeleteAction::make()
                    ->label('Borrar el torneo')
                    ->record(fn (): Torneo => $this->torneo())
                    ->successRedirectUrl(fn (): string => TorneoResource::getUrl('index')),
            ])
                ->label('Más')
                ->icon(Heroicon::OutlinedEllipsisHorizontal)
                ->color('gray')
                ->button()
                ->visible(fn (): bool => Gate::allows('update', $this->torneo())),
            ActionGroup::make($this->accionesDeEstado())
                ->label(fn (): string => 'Estado: '.$this->torneo()->estado->etiqueta())
                ->icon(Heroicon::OutlinedFlag)
                ->color(fn (): string => $this->torneo()->estado->color())
                ->button()
                ->visible(fn (): bool => Gate::allows('update', $this->torneo())),
        ];
    }

    /**
     * Pasar al estado siguiente o volver al anterior: es lo único que
     * permite el ciclo de vida.
     *
     * @return list<Action>
     */
    private function accionesDeEstado(): array
    {
        $acciones = [];

        foreach ([['siguiente', 'Pasar a'], ['anterior', 'Volver a']] as [$paso, $verbo]) {
            $acciones[] = Action::make("estado_{$paso}")
                ->label(fn (): string => $verbo.' «'.($this->estadoDelPaso($paso)?->etiqueta() ?? '').'»')
                ->icon($paso === 'siguiente' ? Heroicon::OutlinedArrowRight : Heroicon::OutlinedArrowLeft)
                ->visible(fn (): bool => $this->estadoDelPaso($paso) !== null)
                ->requiresConfirmation()
                ->modalHeading(fn (): string => $verbo.' «'.($this->estadoDelPaso($paso)?->etiqueta() ?? '').'»')
                ->modalDescription(fn (): string => (! $this->torneo()->estado->publicado() && ($this->estadoDelPaso($paso)?->publicado() ?? false) ? 'El torneo se publica en la web. ' : '').self::queSupone($this->estadoDelPaso($paso)))
                ->action(function (Action $action) use ($paso): void {
                    $estado = $this->estadoDelPaso($paso);

                    if ($estado === null) {
                        return;
                    }

                    try {
                        app(CambiarEstadoDelTorneo::class)->handle($this->torneo(), $estado, $this->usuarioDelTorneo());
                    } catch (ErrorDeCompeticion $e) {
                        Avisos::error('No se ha podido cambiar el estado', [$e->getMessage()]);
                        $action->halt();

                        return;
                    }

                    $this->torneo()->refresh();
                    if ($this instanceof EditRecord) {
                        $this->fillForm();
                    }
                    Avisos::hecho('El torneo está en «'.$estado->etiqueta().'»');
                });
        }

        return $acciones;
    }

    private function estadoDelPaso(string $paso): ?EstadoTorneo
    {
        return $paso === 'siguiente' ? $this->torneo()->estadoSiguiente() : $this->torneo()->estadoAnterior();
    }

    private static function queSupone(?EstadoTorneo $estado): string
    {
        return match ($estado) {
            EstadoTorneo::Borrador => 'El torneo deja de verse en público.',
            EstadoTorneo::InscripcionAbierta => 'Las parejas pueden inscribirse desde la web.',
            EstadoTorneo::InscripcionCerrada => 'Ya no entran inscripciones nuevas. Es el momento de confirmar parejas, fijar cabezas de serie y repasar las fases.',
            EstadoTorneo::Sorteo => 'Se arman los grupos y los cuadros de cada categoría (por sorteo o a mano) y se planifican los horarios. Los jugadores ya ven el torneo en su portal.',
            EstadoTorneo::EnJuego => 'Los horarios se publican y se empiezan a registrar resultados. Hace falta haber planificado antes.',
            EstadoTorneo::Finalizado => 'El torneo termina: quedan las clasificaciones y los cuadros finales.',
            EstadoTorneo::Archivado => 'El torneo pasa al archivo.',
            null => '',
        };
    }

    /** @return Collection<int, Fase> */
    private function fasesDelTorneo(): Collection
    {
        return Fase::query()->whereIn('categoria_id', $this->torneo()->categorias()->pluck('id'))->with('categoria')->get()
            ->sortBy(fn (Fase $f): int => $f->categoria->orden * 100 + $f->orden)->values();
    }

    private function usuarioDelTorneo(): User
    {
        $usuario = Filament::auth()->user();
        assert($usuario instanceof User);

        return $usuario;
    }
}
