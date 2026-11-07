<?php

namespace App\Filament\Resources\Parejas;

use App\Models\Inscripcion;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Contracts\HasTable;

/**
 * La vista para copiar un listado de parejas y pegarlo en WhatsApp: sin
 * la columna de acciones, sin casillas y sin enlaces en las filas, que
 * estorban al seleccionar el texto. Se queda puesta (va en la sesión)
 * hasta que se vuelve a la vista normal.
 *
 * Seleccionar la tabla a mano se lleva también botones y filtros; por eso
 * está además «Copiar para WhatsApp», que da el texto limpio.
 */
final class ParaCopiar
{
    public static function activa(mixed $livewire): bool
    {
        return $livewire instanceof SeCopiaParaPegar && $livewire->estaParaCopiar();
    }

    /** El botón de la cabecera de la tabla que pone y quita la vista. */
    public static function boton(): Action
    {
        return Action::make('paraCopiar')
            ->label(fn (mixed $livewire): string => self::activa($livewire) ? 'Vista normal' : 'Vista para copiar')
            ->tooltip(fn (mixed $livewire): ?string => self::activa($livewire) ? null : 'Quita las acciones, las casillas y los enlaces de la tabla. Para mandarla por WhatsApp, mejor «Copiar para WhatsApp».')
            ->icon(fn (mixed $livewire): Heroicon => self::activa($livewire) ? Heroicon::OutlinedArrowUturnLeft : Heroicon::OutlinedClipboardDocument)
            ->color('gray')
            ->action(function (mixed $livewire): void {
                if ($livewire instanceof SeCopiaParaPegar) {
                    $livewire->alternarParaCopiar();
                }
            });
    }

    /**
     * El texto de las parejas que salen en la tabla (todas las páginas, con
     * la búsqueda y los filtros puestos) y con las columnas visibles, en
     * una ventana con su botón de copiar.
     */
    public static function copiarParaWhatsApp(): Action
    {
        return Action::make('copiarParaWhatsApp')
            ->label('Copiar para WhatsApp')
            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
            ->color('gray')
            ->modalHeading('Copiar para WhatsApp')
            ->modalDescription('Las parejas que salen ahora en la tabla, de todas las páginas y con la búsqueda y los filtros puestos, con las columnas que se ven. Oculte con el botón de columnas lo que no quiera mandar.')
            ->modalContent(fn (HasTable $livewire) => view('filament.tablas.copiar-whatsapp', ['texto' => self::texto($livewire)]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar');
    }

    public static function texto(HasTable $livewire): string
    {
        /** @var iterable<Inscripcion> $parejas */
        $parejas = $livewire->getFilteredSortedTableQuery()?->get() ?? [];

        return TextoParaWhatsApp::componer($parejas, array_keys($livewire->getTable()->getVisibleColumns()));
    }

    /**
     * Las acciones de cada fila (o en bloque), que en la vista para copiar
     * no salen: sin ninguna visible, Filament no pinta su columna ni las
     * casillas.
     *
     * @template T of Action
     *
     * @param  list<T>  $acciones
     * @return list<T>
     */
    public static function ocultando(array $acciones): array
    {
        foreach ($acciones as $accion) {
            $accion->hidden(fn (mixed $livewire): bool => self::activa($livewire));
        }

        return $acciones;
    }

    /** El enlace de la fila, que en la vista para copiar no se pone. */
    public static function enlace(mixed $livewire, string $url): ?string
    {
        return self::activa($livewire) ? null : $url;
    }
}
