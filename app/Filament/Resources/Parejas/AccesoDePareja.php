<?php

namespace App\Filament\Resources\Parejas;

use App\Actions\Jugadores\AccesosDirectos;
use App\Filament\Avisos;
use App\Models\Inscripcion;
use App\Models\User;
use chillerlan\QRCode\QRCode;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/**
 * Los botones de los enlaces de acceso directo en los listados de
 * parejas: en cada una, crear el suyo o verlo para copiarlo; arriba, crear
 * o borrar los de todas las parejas que salen en la tabla (con los filtros
 * puestos). Esperan cargado `accesoDirecto`.
 */
final class AccesoDePareja
{
    public static function crear(): Action
    {
        return Action::make('crearAcceso')
            ->label('Crear enlace')
            ->icon(Heroicon::OutlinedLink)
            ->color('gray')
            ->tooltip('Un enlace para que la pareja entre en su portal sin correo ni contraseña. No caduca.')
            ->visible(fn (Inscripcion $record): bool => $record->accesoDirecto === null)
            ->action(function (Inscripcion $record): void {
                $acceso = app(AccesosDirectos::class)->crear($record, self::usuario());

                if ($acceso === null) {
                    Avisos::error('No se ha creado el enlace', ['La pareja no tiene jugadores.']);
                } else {
                    Avisos::hecho('Enlace creado', 'Pulsa «Enlace» en la pareja para verlo y copiarlo.');
                }
            });
    }

    public static function ver(): Action
    {
        return Action::make('verAcceso')
            ->label('Enlace')
            ->icon(Heroicon::OutlinedLink)
            ->color('primary')
            ->visible(fn (Inscripcion $record): bool => $record->accesoDirecto !== null)
            ->modalHeading(fn (Inscripcion $record): string => 'Enlace de acceso · '.$record->participante->nombre)
            ->modalDescription('Quien lo abra (o escanee el QR) entra en el portal de la pareja sin correo ni contraseña, y no caduca: dáselo solo a la pareja. Para que deje de valer, «Borrar todos los enlaces».')
            ->modalContent(function (Inscripcion $record) {
                $acceso = $record->accesoDirecto;
                assert($acceso !== null);

                $url = AccesosDirectos::url($acceso);

                return view('filament.tablas.enlace-de-acceso', [
                    'url' => $url,
                    'qr' => self::qr($url),
                    'jugador' => $acceso->jugador()->first()?->nombre_completo,
                    'ultimoUso' => $acceso->ultimo_uso_en?->translatedFormat('j \d\e F, H:i'),
                ]);
            })
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar');
    }

    public static function crearTodos(): Action
    {
        return Action::make('crearAccesos')
            ->label('Crear todos los enlaces')
            ->icon(Heroicon::OutlinedLink)
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading('Crear los enlaces de acceso')
            ->modalDescription('Se crea el enlace de cada pareja que sale en la tabla (con la búsqueda y los filtros puestos) y aún no lo tiene. Las de baja, no. Los que ya hay no cambian.')
            ->modalSubmitActionLabel('Crear')
            ->action(function (HasTable $livewire): void {
                $creados = app(AccesosDirectos::class)->crearTodos(self::parejas($livewire), self::usuario());

                Avisos::hecho($creados === 1 ? '1 enlace creado' : "{$creados} enlaces creados");
            });
    }

    public static function borrarTodos(): Action
    {
        return Action::make('borrarAccesos')
            ->label('Borrar todos los enlaces')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Borrar los enlaces de acceso')
            ->modalDescription('Se borran los enlaces de las parejas que salen en la tabla (con la búsqueda y los filtros puestos): dejan de valer en cuanto se borran. Quien ya hubiera entrado sigue dentro hasta que salga.')
            ->modalSubmitActionLabel('Borrar')
            ->action(function (HasTable $livewire): void {
                $borrados = app(AccesosDirectos::class)->borrarTodos(self::parejas($livewire));

                Avisos::hecho($borrados === 1 ? '1 enlace borrado' : "{$borrados} enlaces borrados");
            });
    }

    /** @return Builder<Inscripcion> */
    private static function parejas(HasTable $livewire): Builder
    {
        /** @var Builder<Inscripcion> $consulta */
        $consulta = $livewire->getFilteredTableQuery() ?? Inscripcion::query()->whereRaw('1 = 0');

        return $consulta;
    }

    /** El QR del enlace, como imagen SVG en data URI; nulo si no se puede. */
    private static function qr(string $url): ?string
    {
        try {
            $qr = (new QRCode)->render($url);

            return is_string($qr) ? $qr : null;
        } catch (Throwable) {
            return null;
        }
    }

    private static function usuario(): ?User
    {
        $usuario = Filament::auth()->user();

        return $usuario instanceof User ? $usuario : null;
    }
}
