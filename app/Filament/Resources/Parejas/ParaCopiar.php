<?php

namespace App\Filament\Resources\Parejas;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

/**
 * La vista para copiar un listado de parejas y pegarlo en WhatsApp: sin
 * la columna de acciones, sin casillas y sin enlaces en las filas, que
 * estorban al seleccionar el texto. Se queda puesta (va en la sesión)
 * hasta que se vuelve a la vista normal.
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
            ->tooltip(fn (mixed $livewire): ?string => self::activa($livewire) ? null : 'Quita las acciones, las casillas y los enlaces para seleccionar la tabla y pegarla en WhatsApp. Las columnas se ocultan con el botón de columnas.')
            ->icon(fn (mixed $livewire): Heroicon => self::activa($livewire) ? Heroicon::OutlinedArrowUturnLeft : Heroicon::OutlinedClipboardDocument)
            ->color('gray')
            ->action(function (mixed $livewire): void {
                if ($livewire instanceof SeCopiaParaPegar) {
                    $livewire->alternarParaCopiar();
                }
            });
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
