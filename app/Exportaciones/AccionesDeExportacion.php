<?php

namespace App\Exportaciones;

use App\Models\Categoria;
use App\Models\Torneo;
use App\Tenancy\OrganizadorActual;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Los botones de exportar del panel. Si el plan del organizador no incluye
 * exportaciones, salen apagados y lo dicen.
 */
final class AccionesDeExportacion
{
    public static function pdfDeCategoria(): Action
    {
        return self::base('pdf_categoria', 'Clasificación y cuadro en PDF', Heroicon::OutlinedDocumentArrowDown)
            ->action(fn (Categoria $record): StreamedResponse => self::descargar(app(PdfDeCategoria::class)->generar($record), app(PdfDeCategoria::class)->nombreDeArchivo($record), 'application/pdf'));
    }

    public static function pdfDeHorarios(): Action
    {
        return self::base('pdf_horarios', 'Horarios en PDF', Heroicon::OutlinedCalendarDays)
            ->action(fn (Torneo $record): StreamedResponse => self::descargar(app(PdfDeHorarios::class)->generar($record), app(PdfDeHorarios::class)->nombreDeArchivo($record), 'application/pdf'));
    }

    public static function csvDeInscritos(): Action
    {
        return self::base('csv_inscritos', 'Inscritos en CSV', Heroicon::OutlinedTableCells)
            ->action(fn (Torneo $record): StreamedResponse => self::descargar(app(CsvDeInscritos::class)->generar($record), app(CsvDeInscritos::class)->nombreDeArchivo($record), 'text/csv; charset=UTF-8'));
    }

    public static function permitidas(): bool
    {
        $actual = app(OrganizadorActual::class);

        // Consulta fresca: el plan puede cambiar con la sesión abierta.
        return $actual->hay() && (bool) $actual->organizador()->plan()->value('exportaciones');
    }

    private static function base(string $nombre, string $etiqueta, Heroicon $icono): Action
    {
        return Action::make($nombre)
            ->label($etiqueta)
            ->icon($icono)
            ->color('gray')
            ->disabled(fn (): bool => ! self::permitidas())
            ->tooltip(fn (): ?string => self::permitidas() ? null : 'Su plan no incluye exportaciones.');
    }

    private static function descargar(string $contenido, string $nombre, string $tipo): StreamedResponse
    {
        return response()->streamDownload(function () use ($contenido): void {
            echo $contenido;
        }, $nombre, ['Content-Type' => $tipo]);
    }
}
