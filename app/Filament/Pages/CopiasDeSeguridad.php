<?php

namespace App\Filament\Pages;

use App\Copias\CopiaDeSeguridad;
use App\Filament\Avisos;
use App\Models\User;
use App\Tenancy\OrganizadorActual;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use UnitEnum;

/**
 * Las copias de seguridad de la organización: hacer una ahora, descargar
 * cualquiera de las últimas treinta y saber que cada noche se hace una
 * sola. Cada copia es un zip con la base (un .sql que se restaura en
 * cualquier MySQL o SQLite) y los archivos subidos.
 */
class CopiasDeSeguridad extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|UnitEnum|null $navigationGroup = 'Organización';

    protected static ?int $navigationSort = 95;

    protected static ?string $title = 'Copias de seguridad';

    protected static ?string $slug = 'copias';

    protected string $view = 'filament.pages.copias';

    public static function canAccess(): bool
    {
        $usuario = Filament::auth()->user();

        return $usuario instanceof User && $usuario->esPropietario();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('hacer')
                ->label('Hacer una copia ahora')
                ->icon(Heroicon::OutlinedArchiveBoxArrowDown)
                ->action(function (CopiaDeSeguridad $copias): void {
                    try {
                        $ruta = $copias->deOrganizador(app(OrganizadorActual::class)->organizador());
                    } catch (Throwable $e) {
                        report($e);
                        Avisos::error('No se ha podido hacer la copia', [$e->getMessage()]);

                        return;
                    }

                    Avisos::hecho('Copia hecha', basename($ruta));
                }),
        ];
    }

    public function descargarAction(): Action
    {
        return Action::make('descargar')
            ->label('Descargar')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->link()
            ->action(function (array $arguments): ?StreamedResponse {
                $archivo = CopiaDeSeguridad::carpeta(app(OrganizadorActual::class)->organizador()).'/'.basename((string) ($arguments['archivo'] ?? ''));

                if (! str_ends_with($archivo, '.zip') || ! Storage::disk('local')->exists($archivo)) {
                    Avisos::error('Esa copia ya no está');

                    return null;
                }

                return Storage::disk('local')->download($archivo);
            });
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return [
            'copias' => CopiaDeSeguridad::listar(app(OrganizadorActual::class)->organizador()),
            'conservar' => CopiaDeSeguridad::CONSERVAR,
        ];
    }
}
