<?php

namespace App\Filament\Competicion;

use App\Actions\Competicion\CambiarCruces;
use App\Competicion\Errores\ErrorDeCompeticion;
use App\Competicion\EsquemaDeCuadro;
use App\Enums\EstadoFase;
use App\Enums\EstadoParticipante;
use App\Enums\TipoDeFase;
use App\Filament\Avisos;
use App\Models\Clasificacion;
use App\Models\Fase;
use App\Models\Participante;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Fieldset;
use Filament\Support\Icons\Heroicon;

/**
 * El diálogo para cambiar a mano las parejas de los cruces de un cuadro
 * ya hecho: un selector por hueco de la primera ronda, con la posición
 * de cada pareja en su grupo para orientarse. Vacío es un bye.
 */
final class CambioDeCruces
{
    public static function accion(): Action
    {
        return Action::make('cambiarCruces')
            ->label('Cambiar cruces')
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->color('gray')
            ->visible(fn (Fase $record): bool => $record->tipo === TipoDeFase::Eliminatoria
                && $record->estado !== EstadoFase::Cerrada
                && $record->partidos()->where('ronda', 1)->exists()
                && ! CambiarCruces::yaSeJuega($record))
            ->modalHeading(fn (Fase $record): string => 'Cruces de «'.$record->nombre.'»')
            ->modalDescription('Cambia la pareja de cualquier hueco de la primera ronda: muévela a otro cruce, intercambia dos o pon a otra de la categoría. Un hueco vacío es un bye: la otra pasa sola. Los partidos conservan su hora y su pista. Se puede mientras no se haya jugado ningún partido del cuadro.')
            ->modalSubmitActionLabel('Guardar cruces')
            ->modalWidth('3xl')
            ->fillForm(fn (Fase $record): array => self::datos($record))
            ->schema(fn (Fase $record): array => self::formulario($record))
            ->action(function (Fase $record, array $data, Action $action): void {
                $usuario = Filament::auth()->user();
                assert($usuario instanceof User);

                $huecos = [];
                for ($i = 0; $i < count(CambiarCruces::huecos($record)); $i++) {
                    $valor = $data["hueco_{$i}"] ?? null;
                    $huecos[] = is_numeric($valor) ? (int) $valor : null;
                }

                try {
                    $cambia = app(CambiarCruces::class)->handle($record, $huecos, $usuario);
                } catch (ErrorDeCompeticion $e) {
                    Avisos::error('No se han cambiado los cruces', [$e->getMessage()]);
                    $action->halt();

                    return;
                }

                if ($cambia) {
                    Avisos::hecho('Cruces guardados', 'Revisa el cuadro y, si hace falta, los horarios.');
                } else {
                    Avisos::hecho('Los cruces no cambian');
                }
            });
    }

    /** @return array<string, string|null> */
    private static function datos(Fase $fase): array
    {
        $datos = [];
        foreach (CambiarCruces::huecos($fase) as $i => $id) {
            $datos["hueco_{$i}"] = $id === null ? null : (string) $id;
        }

        return $datos;
    }

    /** @return list<Fieldset> */
    private static function formulario(Fase $fase): array
    {
        $opciones = self::opciones($fase);
        $huecos = CambiarCruces::huecos($fase);
        $cruces = intdiv(count($huecos), 2);
        $prefijo = EsquemaDeCuadro::prefijo($cruces);

        $campos = [];
        for ($c = 0; $c < $cruces; $c++) {
            $campos[] = Fieldset::make('Cruce '.$prefijo.($cruces > 1 ? $c + 1 : ''))
                ->columns(2)
                ->schema([
                    self::hueco(2 * $c, 'Una pareja', $opciones),
                    self::hueco(2 * $c + 1, 'La otra', $opciones),
                ]);
        }

        return $campos;
    }

    /** @param  array<int, string>  $opciones */
    private static function hueco(int $i, string $nombre, array $opciones): Select
    {
        return Select::make("hueco_{$i}")->label($nombre)
            ->options($opciones)
            ->searchable()
            ->optionsLimit(1000)
            ->placeholder('Bye (la otra pasa sola)');
    }

    /**
     * Las parejas de la categoría que juegan, con su puesto en la fase de
     * grupos de la que sale el cuadro («Los Cracks · 1º A»).
     *
     * @return array<int, string>
     */
    private static function opciones(Fase $fase): array
    {
        $puestos = [];
        if ($fase->fase_origen_id !== null) {
            foreach (Clasificacion::query()->where('fase_id', $fase->fase_origen_id)->with('grupo')->get() as $fila) {
                $puestos[$fila->participante_id] = $fila->posicion.'º'.($fila->grupo !== null ? ' '.$fila->grupo->nombre : '');
            }
        }

        return Participante::query()
            ->where('categoria_id', $fase->categoria_id)
            ->where('estado', EstadoParticipante::Activo->value)
            ->orderBy('nombre')
            ->get()
            ->mapWithKeys(fn (Participante $p): array => [$p->id => $p->nombre.(isset($puestos[$p->id]) ? ' · '.$puestos[$p->id] : '')])
            ->all();
    }
}
