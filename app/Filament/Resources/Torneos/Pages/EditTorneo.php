<?php

namespace App\Filament\Resources\Torneos\Pages;

use App\Actions\Competicion\CambiarEstadoDelTorneo;
use App\Actions\Competicion\GenerarFranjas;
use App\Actions\Competicion\PlanificarHorarios;
use App\Competicion\Datos\Conflicto;
use App\Competicion\Errores\ErrorDeCompeticion;
use App\Enums\EstadoTorneo;
use App\Exportaciones\AccionesDeExportacion;
use App\Filament\Avisos;
use App\Filament\Resources\Torneos\TorneoResource;
use App\Models\Torneo;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

/**
 * La ficha del torneo. Arriba, lo que mueve el torneo: su estado (de uno
 * en uno, con las comprobaciones de CambiarEstadoDelTorneo), la
 * planificación de horarios y la mesa de partidos.
 */
class EditTorneo extends EditRecord
{
    protected static string $resource = TorneoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('partidos')
                ->label('Partidos')
                ->icon(Heroicon::OutlinedTableCells)
                ->color('gray')
                ->url(fn (): string => PartidosDelTorneo::getUrl(['record' => $this->torneo()])),
            Action::make('planificar')
                ->label('Planificar horarios')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->visible(fn (): bool => $this->torneo()->torneoPistas()->exists())
                ->requiresConfirmation()
                ->modalDescription('Se crean las franjas que falten en las pistas del torneo y se pone hora y pista a los partidos pendientes de todas las categorías, respetando los ya programados, el descanso mínimo y que nadie juegue dos partidos a la vez.')
                ->modalSubmitActionLabel('Planificar')
                ->action(function (): void {
                    $torneo = $this->torneo();
                    $franjas = app(GenerarFranjas::class)->handle($torneo);
                    $planificacion = app(PlanificarHorarios::class)->handle($torneo, $this->usuario());

                    $colocados = count($planificacion->asignaciones);
                    $conflictos = $planificacion->conflictos;

                    if ($conflictos === []) {
                        Avisos::hecho($colocados === 0 ? 'No había partidos que colocar' : "{$colocados} partidos con hora y pista", $franjas > 0 ? "Se han creado {$franjas} franjas nuevas." : null);

                        return;
                    }

                    Avisos::error(count($conflictos).' partidos se quedan sin sitio', [
                        "{$colocados} colocados.",
                        ...array_map(fn (Conflicto $c): string => "Partido {$c->partido}: {$c->detalle}", array_slice($conflictos, 0, 5)),
                        count($conflictos) > 5 ? 'Y '.(count($conflictos) - 5).' más.' : '',
                    ]);
                }),
            ActionGroup::make([
                AccionesDeExportacion::pdfDeHorarios(),
                AccionesDeExportacion::csvDeInscritos(),
            ])
                ->label('Exportar')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->button(),
            ActionGroup::make($this->accionesDeEstado())
                ->label(fn (): string => 'Estado: '.$this->torneo()->estado->etiqueta())
                ->icon(Heroicon::OutlinedFlag)
                ->color(fn (): string => $this->torneo()->estado->color())
                ->button(),
            DeleteAction::make(),
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
                ->modalDescription(fn (): string => self::queSupone($this->estadoDelPaso($paso)))
                ->action(function (Action $action) use ($paso): void {
                    $estado = $this->estadoDelPaso($paso);

                    if ($estado === null) {
                        return;
                    }

                    try {
                        app(CambiarEstadoDelTorneo::class)->handle($this->torneo(), $estado, $this->usuario());
                    } catch (ErrorDeCompeticion $e) {
                        Avisos::error('No se ha podido cambiar el estado', [$e->getMessage()]);
                        $action->halt();

                        return;
                    }

                    $this->fillForm();
                    Avisos::hecho('El torneo está en «'.$estado->etiqueta().'»');
                });
        }

        return $acciones;
    }

    private function estadoDelPaso(string $paso): ?EstadoTorneo
    {
        $actual = $this->torneo()->estado;

        return $paso === 'siguiente' ? $actual->siguiente() : $actual->anterior();
    }

    private static function queSupone(?EstadoTorneo $estado): string
    {
        return match ($estado) {
            EstadoTorneo::Borrador => 'El torneo deja de verse en público.',
            EstadoTorneo::InscripcionAbierta => 'El torneo se publica y las parejas pueden inscribirse (la inscripción online llega en la fase 3; mientras, la organización las apunta a mano).',
            EstadoTorneo::InscripcionCerrada => 'Ya no entran inscripciones nuevas. Es el momento de confirmar parejas, fijar cabezas de serie y repasar las fases.',
            EstadoTorneo::Sorteo => 'Se sortean los grupos y se generan los partidos de cada categoría, y se planifican los horarios.',
            EstadoTorneo::EnJuego => 'Los horarios se publican y se empiezan a registrar resultados. Hace falta haber planificado antes.',
            EstadoTorneo::Finalizado => 'El torneo termina: quedan las clasificaciones y los cuadros finales.',
            EstadoTorneo::Archivado => 'El torneo pasa al archivo.',
            null => '',
        };
    }

    private function torneo(): Torneo
    {
        $torneo = $this->getRecord();
        assert($torneo instanceof Torneo);

        return $torneo;
    }

    private function usuario(): User
    {
        $usuario = Filament::auth()->user();
        assert($usuario instanceof User);

        return $usuario;
    }
}
